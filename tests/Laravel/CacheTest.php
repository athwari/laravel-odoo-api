<?php

namespace Athwari\LaravelOdooApi\Tests\Laravel;

use Athwari\LaravelOdooApi\Attributes\Model;
use Athwari\LaravelOdooApi\JsonRpc\Client;
use Athwari\LaravelOdooApi\Odoo;
use Athwari\LaravelOdooApi\Odoo\Endpoint\ObjectEndpoint;
use Athwari\LaravelOdooApi\Odoo\OdooModel;
use Athwari\LaravelOdooApi\OdooApiServiceProvider;
use Athwari\LaravelOdooApi\OdooManager;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;

if (! function_exists(__NAMESPACE__.'\\mockHttpClient')) {
    function mockHttpClient(array $responses): HttpClient
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        return new HttpClient(['handler' => $handlerStack]);
    }
}

if (! function_exists(__NAMESPACE__.'\\jsonRpcResult')) {
    /**
     * @param  mixed  $result
     */
    function jsonRpcResult($result): Response
    {
        return new Response(200, [], json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => $result,
        ]));
    }
}

function replaceOdooEndpoint(Odoo $odoo, array $responses): void
{
    $endpoint = new ObjectEndpoint($odoo->getConfig(), $odoo->getContext(), 1);
    $endpoint->setClient(new Client(
        $odoo->getConfig()->getHost(),
        'object',
        30,
        true,
        mockHttpClient($responses)
    ));

    $odoo->setObjectEndpoint($endpoint, 1);
}

beforeEach(function () {
    $this->app->register(OdooApiServiceProvider::class);

    config()->set('odoo-api', [
        'default' => 'default',
        'connections' => [
            'default' => [
                'database' => 'db_default',
                'host' => 'http://default',
                'username' => 'user',
                'password' => 'pass',
            ],
            'erp' => [
                'database' => 'erp_db',
                'host' => 'http://erp',
                'username' => 'erp_user',
                'password' => 'erp_pass',
            ],
        ],
    ]);
});

use Athwari\LaravelOdooApi\Attributes\Field;

#[Model('res.partner')]
class CachedPartner extends OdooModel
{
    #[Field]
    public string $name;
}

#[Model('res.partner')]
class ErpCachedPartner extends OdooModel
{
    protected string $connection = 'erp';

    #[Field]
    public string $name;
}

test('cache method prevents subsequent rpc calls for the same get query', function () {
    $manager = $this->app->make(OdooManager::class);

    // 1 call expected (MockHandler throws if called twice)
    replaceOdooEndpoint($manager->connection('default'), [
        jsonRpcResult([
            ['id' => 1, 'name' => 'Cached Partner'],
        ]),
    ]);

    OdooModel::setConnectionResolver($manager);
    Cache::flush();

    $results1 = CachedPartner::query()->cache(60)->get();
    $results2 = CachedPartner::query()->cache(60)->get();

    expect($results1)->toHaveCount(1)
        ->and($results2)->toHaveCount(1)
        ->and($results1[0]->name)->toBe('Cached Partner')
        ->and($results2[0]->name)->toBe('Cached Partner');
});

test('count is cached and prevents subsequent rpc calls', function () {
    $manager = $this->app->make(OdooManager::class);

    replaceOdooEndpoint($manager->connection('default'), [
        jsonRpcResult(42),
    ]);

    OdooModel::setConnectionResolver($manager);
    Cache::flush();

    $count1 = CachedPartner::query()->cache(60)->count();
    $count2 = CachedPartner::query()->cache(60)->count();

    expect($count1)->toBe(42)
        ->and($count2)->toBe(42);
});

test('pagination uses cached count and cached get', function () {
    $manager = $this->app->make(OdooManager::class);

    replaceOdooEndpoint($manager->connection('default'), [
        jsonRpcResult(20), // count
        jsonRpcResult([    // get
            ['id' => 1, 'name' => 'Page 1 Item'],
        ]),
    ]);

    OdooModel::setConnectionResolver($manager);
    Cache::flush();

    $page1 = CachedPartner::query()->cache(60)->paginate(15, 'page', 1);
    $page1Cached = CachedPartner::query()->cache(60)->paginate(15, 'page', 1);

    expect($page1->total())->toBe(20)
        ->and($page1Cached->total())->toBe(20)
        ->and($page1->items()[0]->name)->toBe('Page 1 Item')
        ->and($page1Cached->items()[0]->name)->toBe('Page 1 Item');
});

test('custom cache key is respected', function () {
    $manager = $this->app->make(OdooManager::class);

    replaceOdooEndpoint($manager->connection('default'), [
        jsonRpcResult([
            ['id' => 1, 'name' => 'Custom Key'],
        ]),
    ]);

    OdooModel::setConnectionResolver($manager);
    Cache::flush();

    CachedPartner::query()->cache(60, 'my_custom_key')->get();
    CachedPartner::query()->cache(60, 'my_custom_key')->get();

    expect(Cache::has('odoo_cache:my_custom_key:get'))->toBeTrue();
});

test('changing query parameters invalidates deterministic cache key', function () {
    $manager = $this->app->make(OdooManager::class);

    // 2 calls expected
    replaceOdooEndpoint($manager->connection('default'), [
        jsonRpcResult([['id' => 1]]),
        jsonRpcResult([['id' => 2]]),
    ]);

    OdooModel::setConnectionResolver($manager);
    Cache::flush();

    $res1 = CachedPartner::query()->cache(60)->where('id', '=', 1)->get();
    $res2 = CachedPartner::query()->cache(60)->where('id', '=', 2)->get();

    expect($res1)->toHaveCount(1)
        ->and($res1[0]->id)->toBe(1)
        ->and($res2)->toHaveCount(1)
        ->and($res2[0]->id)->toBe(2);
});

test('multi connection cache isolation does not share cache', function () {
    $manager = $this->app->make(OdooManager::class);

    replaceOdooEndpoint($manager->connection('default'), [
        jsonRpcResult([['id' => 1, 'name' => 'Default']]),
    ]);

    replaceOdooEndpoint($manager->connection('erp'), [
        jsonRpcResult([['id' => 2, 'name' => 'Erp']]),
    ]);

    OdooModel::setConnectionResolver($manager);
    Cache::flush();

    $defaultRes = CachedPartner::query()->cache(60)->get();
    $erpRes = ErpCachedPartner::query()->cache(60)->get();

    expect($defaultRes[0]->name)->toBe('Default')
        ->and($erpRes[0]->name)->toBe('Erp');
});

test('it does not change cache key after execution context injection', function () {
    $manager = $this->app->make(OdooManager::class);

    // Simulate count() and get() which internally share a builder instance unless cloned
    replaceOdooEndpoint($manager->connection('default'), [
        jsonRpcResult(20), // count response
        jsonRpcResult([['id' => 1, 'name' => 'Cache Integrity']]), // get response
    ]);

    OdooModel::setConnectionResolver($manager);
    Cache::flush();

    $builder = CachedPartner::query()->cache(60);

    // We need the underlying RequestBuilder to call protected generateCacheKey
    $reflection = new \ReflectionClass($builder);
    $property = $reflection->getProperty('builder');
    $property->setAccessible(true);
    $requestBuilder = $property->getValue($builder);

    $method = new \ReflectionMethod($requestBuilder, 'generateCacheKey');
    $method->setAccessible(true);

    // Initial cache key
    $key1 = $method->invoke($requestBuilder, 'get');

    // First execution (count) which triggers ObjectEndpoint::execute and historically mutated Options
    $builder->count();

    // Cache key should remain strictly identical after execution
    $key2 = $method->invoke($requestBuilder, 'get');

    expect($key1)->toBe($key2);
});

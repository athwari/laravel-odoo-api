<?php

namespace Athwari\LaravelOdooApi\Tests\Laravel;

use Athwari\LaravelOdooApi\Events\OdooRecordCreated;
use Athwari\LaravelOdooApi\Events\OdooRecordDeleted;
use Athwari\LaravelOdooApi\Events\OdooRecordUpdated;
use Athwari\LaravelOdooApi\Exceptions\OdooModelException;
use Athwari\LaravelOdooApi\JsonRpc\Client;
use Athwari\LaravelOdooApi\Odoo;
use Athwari\LaravelOdooApi\Odoo\Config;
use Athwari\LaravelOdooApi\Odoo\Context;
use Athwari\LaravelOdooApi\Odoo\Endpoint\ObjectEndpoint;
use Athwari\LaravelOdooApi\Odoo\OdooModel;
use Athwari\LaravelOdooApi\OdooApiServiceProvider;
use Athwari\LaravelOdooApi\Tests\Models\TestPartner;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Event;

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

beforeEach(function () {
    $this->app->register(OdooApiServiceProvider::class);
    OdooModel::setConnectionResolver(null);
});

$bindOdoo = function (array $responses): Odoo {
    $config = new Config('test_db', 'https://example.odoo.com', 'admin', 'admin');
    $endpoint = new ObjectEndpoint($config, new Context(), 1);
    $endpoint->setClient(new Client(
        'https://example.odoo.com',
        'object',
        30,
        true,
        mockHttpClient($responses),
    ));

    $odoo = (new Odoo($config))->setObjectEndpoint($endpoint, 1);

    TestPartner::boot($odoo);

    return $odoo;
};

test('model created event fires', function () use ($bindOdoo) {
    Event::fake();

    $bindOdoo([
        jsonRpcResult(77),
    ]);

    $partner = new TestPartner();
    $partner->name = 'New Co';
    $partner->save();

    Event::assertDispatched(OdooRecordCreated::class, function ($event) use ($partner) {
        return $event->model === $partner;
    });
});

test('model updated event fires', function () use ($bindOdoo) {
    Event::fake();

    $bindOdoo([
        jsonRpcResult(true),
    ]);

    $partner = new TestPartner();
    $partner->id = 5;
    $partner->name = 'Updated Co';
    $partner->save();

    Event::assertDispatched(OdooRecordUpdated::class, function ($event) use ($partner) {
        return $event->model === $partner;
    });
});

test('model deleted event fires', function () use ($bindOdoo) {
    Event::fake();

    $bindOdoo([
        jsonRpcResult(true),
    ]);

    $partner = new TestPartner();
    $partner->id = 5;
    $partner->name = 'To Be Deleted';

    $result = $partner->delete();

    expect($result)->toBeTrue();
    Event::assertDispatched(OdooRecordDeleted::class, function ($event) use ($partner) {
        return $event->model === $partner;
    });
});

test('failed RPC does not fire "after" event', function () use ($bindOdoo) {
    Event::fake();

    $bindOdoo([
        jsonRpcResult(false), // Simulate failed write
    ]);

    $partner = new TestPartner();
    $partner->id = 5;

    try {
        $partner->save();
    } catch (OdooModelException $e) {
        // Expected
    }

    Event::assertNotDispatched(OdooRecordUpdated::class);
});

test('raw RequestBuilder create does not fire model events', function () {
    Event::fake();

    $config = new Config('test_db', 'https://example.odoo.com', 'admin', 'admin');
    $endpoint = new ObjectEndpoint($config, new Context(), 1);
    $endpoint->setClient(new Client(
        'https://example.odoo.com',
        'object',
        30,
        true,
        mockHttpClient([
            jsonRpcResult(124),
        ]),
    ));

    $id = $endpoint->model('res.partner')->create(['name' => 'No Event Co']);

    expect($id)->toBe(124);
    Event::assertNotDispatched(OdooRecordCreated::class);
});

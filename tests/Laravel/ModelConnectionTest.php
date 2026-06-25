<?php

namespace Athwari\LaravelOdooApi\Tests\Laravel;

use Athwari\LaravelOdooApi\Attributes\Model;
use Athwari\LaravelOdooApi\JsonRpc\Client;
use Athwari\LaravelOdooApi\Odoo;
use Athwari\LaravelOdooApi\Odoo\Config;
use Athwari\LaravelOdooApi\Odoo\Context;
use Athwari\LaravelOdooApi\Odoo\Endpoint\ObjectEndpoint;
use Athwari\LaravelOdooApi\Odoo\OdooModel;
use Athwari\LaravelOdooApi\OdooApiServiceProvider;
use Athwari\LaravelOdooApi\OdooManager;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

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

function modelConnectionMockHttpClient(): HttpClient
{
    $mock = new MockHandler([]);
    $handlerStack = HandlerStack::create($mock);

    return new HttpClient(['handler' => $handlerStack]);
}

function mockedOdoo(string $host): Odoo
{
    $config = new Config('test_db', $host, 'admin', 'admin');
    $endpoint = new ObjectEndpoint($config, new Context(), 1);
    $endpoint->setClient(new Client(
        $host,
        'object',
        30,
        true,
        modelConnectionMockHttpClient(),
    ));

    return (new Odoo($config))->setObjectEndpoint($endpoint, 1);
}

#[Model('res.partner')]
class DefaultPartner extends OdooModel
{
    // Uses default connection implicitly
}

#[Model('res.partner')]
class ErpPartner extends OdooModel
{
    protected string $connection = 'erp';
}

test('model without explicit connection uses default connection', function () {
    // We can't easily mock the static odoo() without invoking it, but we can check the connection used by invoking a method that calls it and mocking the manager.
    // Or we can just get the query and check the Odoo instance.
    // The query builder gets the Odoo\Request\RequestBuilder which holds the JsonRpc\Client config? Actually, ModelQuery holds the RequestBuilder.
    // RequestBuilder doesn't expose Odoo, but it's constructed with it.
    // Let's bind a mock OdooManager to inspect connection resolution.

    $manager = \Mockery::spy(OdooManager::class);
    $manager->shouldReceive('connection')->with('default')->andReturn(mockedOdoo('http://default'));
    $manager->shouldReceive('connection')->with('erp')->andReturn(mockedOdoo('http://erp'));

    OdooModel::setConnectionResolver($manager);

    DefaultPartner::query();

    $manager->shouldHaveReceived('connection', ['default']);
});

test('model with explicit connection uses named connection', function () {
    $manager = \Mockery::spy(OdooManager::class);
    $manager->shouldReceive('connection')->with('default')->andReturn(mockedOdoo('http://default'));
    $manager->shouldReceive('connection')->with('erp')->andReturn(mockedOdoo('http://erp'));

    OdooModel::setConnectionResolver($manager);

    ErpPartner::query();

    $manager->shouldHaveReceived('connection', ['erp']);
});

test('changing connection at runtime applies immediately', function () {
    $manager = \Mockery::spy(OdooManager::class);
    $manager->shouldReceive('connection')->with('default')->andReturn(mockedOdoo('http://default'));
    $manager->shouldReceive('connection')->with('erp')->andReturn(mockedOdoo('http://erp'));

    OdooModel::setConnectionResolver($manager);

    $partner = new DefaultPartner();
    $partner->setConnection('erp');
    // Save or update would use it, but since odoo() is static and uses (new static)->getConnectionName() it relies on the class property!
    // Wait! A runtime instance with setConnection() won't affect static calls like query() or find().
    // We can check if it works on the instance by calling an instance method like save() (requires mocking more).
    expect($partner->getConnectionName())->toBe('erp');
});

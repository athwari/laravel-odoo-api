<?php

namespace Athwari\LaravelOdooApi\Tests\Laravel;

use Athwari\LaravelOdooApi\Facades\Odoo;
use Athwari\LaravelOdooApi\JsonRpc\Client;

beforeEach(function () {
    config()->set('odoo-api', [
        'default' => 'default',
        'connections' => [
            'default' => [
                'host' => 'http://localhost',
                'database' => 'test',
                'username' => 'test',
                'password' => 'test',
            ],
        ],
    ]);
});

it('fakes the odoo transport', function () {
    $fake = Odoo::fake();
    $fake->shouldReceive('res.partner', 'search_read')->andReturn([['id' => 1]]);

    $results = Odoo::model('res.partner')->get();

    expect($results)->toHaveCount(1);
    $fake->assertSent(function ($model, $method) {
        return $model === 'res.partner' && $method === 'search_read';
    });
});

it('does not leak fake transport to subsequent tests', function () {
    // This test runs AFTER the one above.
    // Odoo::fake() should not be active here because the container was reset.
    $odoo = Odoo::connection();

    $client = $odoo->getCommonEndpoint()->getClient();
    // It should be a real JsonRpc\Client, not OdooFake.
    expect($client)->toBeInstanceOf(Client::class);
});

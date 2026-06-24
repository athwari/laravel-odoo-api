<?php

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\Exceptions\ValidationException;
use Athwari\LaravelOdooApi\JsonRpc\Client;
use Athwari\LaravelOdooApi\Odoo\Config;
use Athwari\LaravelOdooApi\Odoo\Context;
use Athwari\LaravelOdooApi\Odoo\Endpoint\ObjectEndpoint;

$makeEndpoint = function (array $responses): ObjectEndpoint {
    $config = new Config('test_db', 'https://example.odoo.com', 'admin', 'admin');
    $endpoint = new ObjectEndpoint($config, new Context(), 1);

    $client = new Client(
        'https://example.odoo.com',
        'object',
        30,
        true,
        $this->mockHttpClient($responses),
    );

    return $endpoint->setClient($client);
};

test('delete throws without a where clause', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, []);

    expect(fn () => $endpoint->model('res.partner')->delete())
        ->toThrow(ValidationException::class);
});

test('write throws without a where clause', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, []);

    expect(fn () => $endpoint->model('res.partner')->write(['active' => false]))
        ->toThrow(ValidationException::class);
});

test('delete succeeds with a where clause', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, [
        $this->jsonRpcResult([1, 2]), // search() for matching ids
        $this->jsonRpcResult(true),   // unlink()
    ]);

    $result = $endpoint->model('res.partner')
        ->where('active', '=', false)
        ->delete();

    expect($result)->toBeTrue();
});

test('write succeeds with a where clause', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, [
        $this->jsonRpcResult([1]),  // search() for matching ids
        $this->jsonRpcResult(true), // write()
    ]);

    $result = $endpoint->model('res.partner')
        ->where('id', '=', 1)
        ->write(['active' => false]);

    expect($result)->toBeTrue();
});

test('delete with no matching records is a noop success', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, [
        $this->jsonRpcResult([]), // search() finds nothing
    ]);

    $result = $endpoint->model('res.partner')
        ->where('id', '=', 999999)
        ->delete();

    expect($result)->toBeTrue();
});

test('create does not require a where clause', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, [
        $this->jsonRpcResult(123),
    ]);

    $id = $endpoint->model('res.partner')->create(['name' => 'New Co']);

    expect($id)->toBe(123);
});

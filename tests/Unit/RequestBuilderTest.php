<?php

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\Exceptions\ValidationException;
use Athwari\LaravelOdooApi\JsonRpc\Client;
use Athwari\LaravelOdooApi\Odoo\Config;
use Athwari\LaravelOdooApi\Odoo\Context;
use Athwari\LaravelOdooApi\Odoo\Endpoint\ObjectEndpoint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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

test('collect returns an Illuminate Support Collection', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, [
        $this->jsonRpcResult([
            ['id' => 1, 'name' => 'Test 1'],
            ['id' => 2, 'name' => 'Test 2'],
        ]),
    ]);

    $collection = $endpoint->model('res.partner')->collect();

    expect($collection)->toBeInstanceOf(Collection::class)
        ->and($collection->count())->toBe(2)
        ->and((array) $collection->first())->toBe(['id' => 1, 'name' => 'Test 1']);
});

test('paginate returns a LengthAwarePaginator of generic objects', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, [
        $this->jsonRpcResult(15), // count()
        $this->jsonRpcResult([
            ['id' => 1, 'name' => 'Test 1'],
            ['id' => 2, 'name' => 'Test 2'],
        ]), // get()
    ]);

    $paginator = $endpoint->model('res.partner')->paginate(2);

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(15)
        ->and($paginator->perPage())->toBe(2)
        ->and($paginator->items())->toHaveCount(2)
        ->and((array) $paginator->items()[0])->toBe(['id' => 1, 'name' => 'Test 1']);
});

test('paginate returns empty paginator when count is zero', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, [
        $this->jsonRpcResult(0), // count()
    ]);

    $paginator = $endpoint->model('res.partner')->paginate(2);

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(0)
        ->and($paginator->items())->toBeEmpty();
});

test('chunk loops over paginated results and stops early on false', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, [
        // Page 1
        $this->jsonRpcResult([
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
        ]),
        // Page 2
        $this->jsonRpcResult([
            ['id' => 3, 'name' => 'C'],
            ['id' => 4, 'name' => 'D'],
        ]),
    ]);

    $iterations = 0;
    $result = $endpoint->model('res.partner')->chunk(2, function ($results, $page) use (&$iterations) {
        $iterations++;
        if ($page === 2) {
            return false;
        }
    });

    expect($result)->toBeFalse()
        ->and($iterations)->toBe(2);
});

test('chunk loops until empty results are found', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, [
        $this->jsonRpcResult([['id' => 1]]),
        $this->jsonRpcResult([]),
    ]);

    $iterations = 0;
    $result = $endpoint->model('res.partner')->chunk(1, function ($results) use (&$iterations) {
        $iterations++;
    });

    expect($result)->toBeTrue()
        ->and($iterations)->toBe(1);
});

test('createMany sends array of values to create endpoint and returns ids', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, [
        $this->jsonRpcResult([10, 11]),
    ]);

    $result = $endpoint->model('res.partner')->createMany([
        ['name' => 'A'],
        ['name' => 'B'],
    ]);

    expect($result)->toBe([10, 11]);
});

test('createMany throws on empty data', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, []);

    expect(fn () => $endpoint->model('res.partner')->createMany([]))
        ->toThrow(ValidationException::class);
});

test('writeMany groups identical payloads into a single rpc call', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, [
        $this->jsonRpcResult(true), // One call for the 'done' state group
    ]);

    $result = $endpoint->model('res.partner')->writeMany([
        ['id' => 1, 'values' => ['state' => 'done']],
        ['id' => 2, 'values' => ['state' => 'done']],
    ]);

    expect($result)->toBeTrue();
});

test('writeMany makes multiple calls for different payloads', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, [
        $this->jsonRpcResult(true), // Call for 'done'
        $this->jsonRpcResult(true), // Call for 'draft'
    ]);

    $result = $endpoint->model('res.partner')->writeMany([
        ['id' => 1, 'values' => ['state' => 'done']],
        ['id' => 2, 'values' => ['state' => 'draft']],
    ]);

    expect($result)->toBeTrue();
});

test('writeMany ignores empty values in payload', function () use ($makeEndpoint) {
    // Since values are empty, it should do nothing and make no RPC calls
    $endpoint = $makeEndpoint->call($this, []);

    $result = $endpoint->model('res.partner')->writeMany([
        ['id' => 1, 'values' => []],
    ]);

    expect($result)->toBeTrue();
});

test('writeMany throws on empty data', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, []);

    expect(fn () => $endpoint->model('res.partner')->writeMany([]))
        ->toThrow(ValidationException::class);
});

test('writeMany throws on invalid payload structure', function () use ($makeEndpoint) {
    $endpoint = $makeEndpoint->call($this, []);

    expect(fn () => $endpoint->model('res.partner')->writeMany([
        ['id' => 1], // missing 'values'
    ]))->toThrow(ValidationException::class);

    expect(fn () => $endpoint->model('res.partner')->writeMany([
        ['values' => ['state' => 'done']], // missing 'id'
    ]))->toThrow(ValidationException::class);
});

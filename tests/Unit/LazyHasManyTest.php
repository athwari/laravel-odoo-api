<?php

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\JsonRpc\Client;
use Athwari\LaravelOdooApi\Odoo;
use Athwari\LaravelOdooApi\Odoo\Config;
use Athwari\LaravelOdooApi\Odoo\Context;
use Athwari\LaravelOdooApi\Odoo\Endpoint\ObjectEndpoint;
use Athwari\LaravelOdooApi\Odoo\Models\LazyHasMany;
use Athwari\LaravelOdooApi\Tests\Models\TestPartner;

$bindOdoo = function (array $responses): void {
    $config = new Config('test_db', 'https://example.odoo.com', 'admin', 'admin');
    $endpoint = new ObjectEndpoint($config, new Context(), 1);
    $endpoint->setClient(new Client(
        'https://example.odoo.com',
        'object',
        30,
        true,
        $this->mockHttpClient($responses),
    ));

    TestPartner::boot((new Odoo($config))->setObjectEndpoint($endpoint, 1));
};

test('an empty id list never makes a request', function () {
    // No responses queued at all: if ensureLoaded() tried to call
    // read() with an empty id list, MockHandler would throw from
    // running out of queued responses, failing this test.
    $collection = new LazyHasMany(TestPartner::class, []);

    expect($collection->count())->toBe(0);
    expect($collection->toArray())->toBe([]);
});

test('reload forces a fresh fetch', function () use ($bindOdoo) {
    $bindOdoo->call($this, [
        $this->jsonRpcResult([
            (object) ['id' => 1, 'name' => 'First Load', 'email' => null, 'active' => true, 'parent_id' => false, 'child_ids' => []],
        ]),
        $this->jsonRpcResult([
            (object) ['id' => 1, 'name' => 'Second Load', 'email' => null, 'active' => true, 'parent_id' => false, 'child_ids' => []],
        ]),
    ]);

    $collection = new LazyHasMany(TestPartner::class, [1]);

    expect($collection[0]->name)->toBe('First Load');
    expect($collection->isLoaded())->toBeTrue();

    $collection->reload();

    expect($collection->isLoaded())->toBeFalse();
    expect($collection[0]->name)->toBe('Second Load');
});

test('iterator contract', function () use ($bindOdoo) {
    $bindOdoo->call($this, [
        $this->jsonRpcResult([
            (object) ['id' => 1, 'name' => 'A', 'email' => null, 'active' => true, 'parent_id' => false, 'child_ids' => []],
            (object) ['id' => 2, 'name' => 'B', 'email' => null, 'active' => true, 'parent_id' => false, 'child_ids' => []],
        ]),
    ]);

    $collection = new LazyHasMany(TestPartner::class, [1, 2]);

    $names = [];
    foreach ($collection as $item) {
        $names[] = $item->name;
    }

    expect($names)->toBe(['A', 'B']);
});

test('array access contract', function () use ($bindOdoo) {
    $bindOdoo->call($this, [
        $this->jsonRpcResult([
            (object) ['id' => 1, 'name' => 'A', 'email' => null, 'active' => true, 'parent_id' => false, 'child_ids' => []],
        ]),
    ]);

    $collection = new LazyHasMany(TestPartner::class, [1]);

    expect(isset($collection[0]))->toBeTrue();
    expect(isset($collection[5]))->toBeFalse();
    expect($collection[5])->toBeNull();
});

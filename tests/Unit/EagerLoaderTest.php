<?php

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\JsonRpc\Client;
use Athwari\LaravelOdooApi\Odoo;
use Athwari\LaravelOdooApi\Odoo\Config;
use Athwari\LaravelOdooApi\Odoo\Context;
use Athwari\LaravelOdooApi\Odoo\Endpoint\ObjectEndpoint;
use Athwari\LaravelOdooApi\Tests\Models\TestPartner;

test('with eagerly loads belongs to relations', function () {
    $config = new Config('test_db', 'https://example.odoo.com', 'admin', 'admin');
    $endpoint = new ObjectEndpoint($config, new Context(), 1);
    
    // We expect exactly TWO RPC calls:
    // 1. searchRead to get the initial partners
    // 2. read to bulk-load their parent_ids
    $client = new Client(
        'https://example.odoo.com',
        'object',
        30,
        true,
        $this->mockHttpClient([
            $this->jsonRpcResult([
                (object) ['id' => 10, 'name' => 'Child A', 'parent_id' => [1, 'Parent']],
                (object) ['id' => 11, 'name' => 'Child B', 'parent_id' => [1, 'Parent']],
                (object) ['id' => 12, 'name' => 'Child C', 'parent_id' => [2, 'Another Parent']],
            ]),
            $this->jsonRpcResult([
                (object) ['id' => 1, 'name' => 'Parent', 'parent_id' => false],
                (object) ['id' => 2, 'name' => 'Another Parent', 'parent_id' => false],
            ]),
        ])
    );
    $endpoint->setClient($client);

    $odoo = (new Odoo($config))->setObjectEndpoint($endpoint, 1);
    TestPartner::boot($odoo);

    $partners = TestPartner::query()->with('parent')->get();

    expect($partners)->toHaveCount(3);
    
    // Parent should be populated without triggering another RPC
    expect($partners[0]->parent->name)->toBe('Parent');
    expect($partners[1]->parent->name)->toBe('Parent');
    expect($partners[2]->parent->name)->toBe('Another Parent');
});

test('with eagerly loads has many relations', function () {
    $config = new Config('test_db', 'https://example.odoo.com', 'admin', 'admin');
    $endpoint = new ObjectEndpoint($config, new Context(), 1);
    
    // We expect exactly TWO RPC calls:
    // 1. searchRead to get the parent partners
    // 2. read to bulk-load all their child_ids
    $client = new Client(
        'https://example.odoo.com',
        'object',
        30,
        true,
        $this->mockHttpClient([
            $this->jsonRpcResult([
                (object) ['id' => 1, 'name' => 'Parent A', 'child_ids' => [10, 11]],
                (object) ['id' => 2, 'name' => 'Parent B', 'child_ids' => [12]],
            ]),
            $this->jsonRpcResult([
                (object) ['id' => 10, 'name' => 'Child A'],
                (object) ['id' => 11, 'name' => 'Child B'],
                (object) ['id' => 12, 'name' => 'Child C'],
            ]),
        ])
    );
    $endpoint->setClient($client);

    $odoo = (new Odoo($config))->setObjectEndpoint($endpoint, 1);
    TestPartner::boot($odoo);

    $partners = TestPartner::query()->with('children')->get();

    expect($partners)->toHaveCount(2);
    
    expect($partners[0]->children->isLoaded())->toBeTrue();
    expect($partners[0]->children)->toHaveCount(2);
    expect($partners[0]->children[0]->name)->toBe('Child A');
    
    expect($partners[1]->children->isLoaded())->toBeTrue();
    expect($partners[1]->children)->toHaveCount(1);
    expect($partners[1]->children[0]->name)->toBe('Child C');
});

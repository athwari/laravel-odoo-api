<?php

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\Exceptions\UndefinedPropertyException;
use Athwari\LaravelOdooApi\JsonRpc\Client;
use Athwari\LaravelOdooApi\Odoo;
use Athwari\LaravelOdooApi\Odoo\Config;
use Athwari\LaravelOdooApi\Odoo\Context;
use Athwari\LaravelOdooApi\Odoo\Endpoint\ObjectEndpoint;
use Athwari\LaravelOdooApi\Tests\Models\TestPartner;

$bindOdoo = function (array $responses): Odoo {
    $config = new Config('test_db', 'https://example.odoo.com', 'admin', 'admin');
    $endpoint = new ObjectEndpoint($config, new Context(), 1);
    $endpoint->setClient(new Client(
        'https://example.odoo.com',
        'object',
        30,
        true,
        $this->mockHttpClient($responses),
    ));

    $odoo = (new Odoo($config))->setObjectEndpoint($endpoint, 1);

    TestPartner::boot($odoo);

    return $odoo;
};

test('find hydrates a model instance', function () use ($bindOdoo) {
    $bindOdoo->call($this, [
        $this->jsonRpcResult([
            (object) [
                'id' => 42,
                'name' => 'Acme Corp',
                'email' => 'hello@acme.test',
                'active' => true,
                'parent_id' => false,
                'child_ids' => [],
            ],
        ]),
    ]);

    $partner = TestPartner::find(42);

    expect($partner)->toBeInstanceOf(TestPartner::class);
    expect($partner->id)->toBe(42);
    expect($partner->name)->toBe('Acme Corp');
    expect($partner->email)->toBe('hello@acme.test');
    expect($partner->active)->toBeTrue();
});

test('find returns null when no record found', function () use ($bindOdoo) {
    $bindOdoo->call($this, [
        $this->jsonRpcResult([]),
    ]);

    expect(TestPartner::find(999))->toBeNull();
});

test('belongs to resolves a related model eagerly', function () use ($bindOdoo) {
    $bindOdoo->call($this, [
        // First read(): the child partner, with a parent_id tuple
        $this->jsonRpcResult([
            (object) [
                'id' => 2,
                'name' => 'Child Co',
                'email' => null,
                'active' => true,
                'parent_id' => [1, 'Parent Co'],
                'child_ids' => [],
            ],
        ]),
        // Second read(): triggered internally by BelongsTo resolving parent_id=1
        $this->jsonRpcResult([
            (object) [
                'id' => 1,
                'name' => 'Parent Co',
                'email' => null,
                'active' => true,
                'parent_id' => false,
                'child_ids' => [2],
            ],
        ]),
    ]);

    $child = TestPartner::find(2);

    expect($child->parentId)->toBe(1);
    expect($child->parent)->toBeInstanceOf(TestPartner::class);
    expect($child->parent->name)->toBe('Parent Co');
});

test('belongs to is null when the relation is unset', function () use ($bindOdoo) {
    $bindOdoo->call($this, [
        $this->jsonRpcResult([
            (object) [
                'id' => 1,
                'name' => 'Standalone Co',
                'email' => null,
                'active' => true,
                'parent_id' => false,
                'child_ids' => [],
            ],
        ]),
    ]);

    $partner = TestPartner::find(1);

    expect($partner->parentId)->toBeNull();
    expect($partner->parent)->toBeNull();
});

test('has many is lazy and not loaded until accessed', function () use ($bindOdoo) {
    $odoo = $bindOdoo->call($this, [
        $this->jsonRpcResult([
            (object) [
                'id' => 1,
                'name' => 'Parent Co',
                'email' => null,
                'active' => true,
                'parent_id' => false,
                'child_ids' => [2, 3],
            ],
        ]),
        // Only consumed if children is actually accessed below. Note:
        // child_ids' own parent_id is left unset here on purpose, to
        // avoid each child eagerly re-resolving its own #[BelongsTo]
        // parent (which would otherwise cascade into further reads —
        // see the eager-BelongsTo recursion note on hydrateBelongsTo()).
        $this->jsonRpcResult([
            (object) ['id' => 2, 'name' => 'Child A', 'email' => null, 'active' => true, 'parent_id' => false, 'child_ids' => []],
            (object) ['id' => 3, 'name' => 'Child B', 'email' => null, 'active' => true, 'parent_id' => false, 'child_ids' => []],
        ]),
    ]);

    $parent = TestPartner::find(1);

    expect($parent->children->isLoaded())->toBeFalse();
    expect($parent->children)->toHaveCount(2);
    expect($parent->children->isLoaded())->toBeTrue();
    expect($parent->children[0]->name)->toBe('Child A');

    unset($odoo);
});

test('belongs to recursion is capped by a depth guard', function () use ($bindOdoo) {
    // Each level's parent_id points back to the same id (1), so without
    // a depth guard this would recurse indefinitely consuming queued
    // mock responses until MockHandler runs out and throws.
    $responses = [];
    for ($i = 0; $i < 10; $i++) {
        $responses[] = $this->jsonRpcResult([
            (object) [
                'id' => 1,
                'name' => 'Self Referential Co',
                'email' => null,
                'active' => true,
                'parent_id' => [1, 'Self Referential Co'],
                'child_ids' => [],
            ],
        ]);
    }

    $bindOdoo->call($this, $responses);

    $partner = TestPartner::find(1);

    // Resolution succeeds up to the depth limit; beyond that the
    // relation is left null instead of recursing further.
    expect($partner)->toBeInstanceOf(TestPartner::class);
    expect($partner->id)->toBe(1);
});

test('save creates a new record', function () use ($bindOdoo) {
    $bindOdoo->call($this, [
        $this->jsonRpcResult(77), // create() returns the new id
    ]);

    $partner = new TestPartner();
    $partner->name = 'New Co';
    $partner->email = 'new@co.test';
    $partner->active = true;

    $partner->save();

    expect($partner->id)->toBe(77);
});

test('save updates an existing record', function () use ($bindOdoo) {
    $bindOdoo->call($this, [
        $this->jsonRpcResult(true), // write() returns true
    ]);

    $partner = new TestPartner();
    $partner->id = 5;
    $partner->name = 'Updated Co';
    $partner->email = 'updated@co.test';
    $partner->active = true;

    $result = $partner->save();

    expect($result)->toBe($partner);
});

test('fill sets known properties', function () {
    $partner = (new TestPartner())->fill(['name' => 'Filled Co', 'active' => false]);

    expect($partner->name)->toBe('Filled Co');
    expect($partner->active)->toBeFalse();
});

test('fill throws for an undefined property', function () {
    (new TestPartner())->fill(['not_a_real_property' => 'value']);
})->throws(UndefinedPropertyException::class);

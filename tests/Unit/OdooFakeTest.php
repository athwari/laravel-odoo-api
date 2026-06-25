<?php

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\Testing\OdooFake;

it('intercepts execution and returns mocked values based on model and method', function () {
    $fake = new OdooFake();

    $fake->shouldReceive('res.partner', 'search_read')->andReturn([['id' => 1, 'name' => 'John']]);

    $result = $fake->execute_kw('test', 1, 'pass', 'res.partner', 'search_read');

    expect($result)->toBeArray()
        ->and($result[0]['name'])->toBe('John');
});

it('throws an exception when an unmocked call is made', function () {
    $fake = new OdooFake();

    $fake->execute_kw('test', 1, 'pass', 'res.partner', 'search_read');
})->throws(\RuntimeException::class);

it('allows asserting that a call was sent', function () {
    $fake = new OdooFake();
    $fake->shouldReceive('res.partner', 'search_read')->andReturn([]);

    $fake->execute_kw('test', 1, 'pass', 'res.partner', 'search_read', [[['id', '=', 1]]]);

    $fake->assertSent(function (string $model, string $method, array $args) {
        return $model === 'res.partner' && $method === 'search_read' && $args[0][0][0] === 'id';
    });
});

it('allows asserting that a call was not sent', function () {
    $fake = new OdooFake();
    $fake->shouldReceive('res.partner', 'search_read')->andReturn([]);

    $fake->assertNotSent(function (string $model, string $method) {
        return $model === 'res.partner' && $method === 'search_read';
    });
});

it('can fake any method and model using wildcards', function () {
    $fake = new OdooFake();
    $fake->shouldReceive('*', '*')->andReturn([['id' => 2]]);

    $result = $fake->execute_kw('test', 1, 'pass', 'res.users', 'read');

    expect($result)->toBeArray()
        ->and($result[0]['id'])->toBe(2);
});

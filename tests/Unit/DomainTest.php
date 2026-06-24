<?php

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Domain;
use RuntimeException;

test('empty domain', function () {
    $domain = new Domain();

    expect($domain->isEmpty())->toBeTrue();
    expect($domain->toArray())->toBe([]);
});

test('single where', function () {
    $domain = (new Domain())->where('name', '=', 'Test');

    expect($domain->toArray())->toBe([['name', '=', 'Test']]);
});

test('multiple where is implicit and', function () {
    $domain = (new Domain())
        ->where('name', '=', 'Test')
        ->where('active', '=', true);

    expect($domain->toArray())->toBe([
        ['name', '=', 'Test'],
        ['active', '=', true],
    ]);
});

test('or where inserts polish notation token', function () {
    $domain = (new Domain())
        ->where('name', '=', 'A')
        ->orWhere('name', '=', 'B');

    expect($domain->toArray())->toBe([
        '|',
        ['name', '=', 'A'],
        ['name', '=', 'B'],
    ]);
});

test('or where as first condition throws', function () {
    (new Domain())->orWhere('name', '=', 'A');
})->throws(RuntimeException::class);

test('add raw appends a raw criterion', function () {
    $domain = (new Domain())
        ->where('active', '=', true)
        ->addRaw(['name', 'ilike', 'test']);

    expect($domain->toArray())->toBe([
        ['active', '=', true],
        ['name', 'ilike', 'test'],
    ]);
});

test('make factory', function () {
    $domain = Domain::make()->where('id', '=', 1);

    expect($domain)->toBeInstanceOf(Domain::class);
    expect($domain->toArray())->toBe([['id', '=', 1]]);
});

test('count', function () {
    $domain = (new Domain())
        ->where('a', '=', 1)
        ->where('b', '=', 2);

    expect($domain->count())->toBe(2);
});

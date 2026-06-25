<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\Odoo\Context;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Options;

test('toArray returns raw options when context is missing', function () {
    $options = (new Options())
        ->limit(25)
        ->offset(5)
        ->setRaw('order', 'name asc');

    expect($options->toArray())->toBe([
        'limit' => 25,
        'offset' => 5,
        'order' => 'name asc',
    ]);
});

test('withContext returns a clone and keeps original options unchanged', function () {
    $options = (new Options())
        ->limit(10)
        ->offset(2);

    $context = new Context('en_US', 'UTC', 3);
    $withContext = $options->withContext($context);

    expect($options->toArray())->toBe([
        'limit' => 10,
        'offset' => 2,
    ]);

    expect($withContext->toArray())->toBe([
        'context' => [
            'lang' => 'en_US',
            'tz' => 'UTC',
            'company_id' => 3,
        ],
        'limit' => 10,
        'offset' => 2,
    ]);
});

test('setRaw can override existing option keys', function () {
    $options = (new Options())
        ->limit(10)
        ->setRaw('limit', 99);

    expect($options->toArray()['limit'])->toBe(99);
});

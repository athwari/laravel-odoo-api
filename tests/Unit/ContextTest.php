<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\Odoo\Context;

test('toArray includes non null context values and preserves false', function () {
    $context = new Context('en_US', 'UTC', 7);
    $context->setContextArg('allowed_company_ids', [7, 8]);
    $context->setContextArg('active_test', false);
    $context->setContextArg('nullable', null);

    expect($context->toArray())->toBe([
        'lang' => 'en_US',
        'tz' => 'UTC',
        'company_id' => 7,
        'allowed_company_ids' => [7, 8],
        'active_test' => false,
    ]);
});

test('clone creates an independent context copy', function () {
    $context = new Context('en_US');
    $context->setContextArg('key', 'original');

    $cloned = $context->clone();
    $cloned->setContextArg('key', 'cloned');

    expect($context->toArray()['key'])->toBe('original')
        ->and($cloned->toArray()['key'])->toBe('cloned');
});

test('setDefaults merges only missing values', function () {
    $context = new Context('en_US', null, null, [
        'keep' => 999,
        'local' => true,
    ]);

    $defaults = new Context('ar_001', 'Asia/Riyadh', 10, [
        'keep' => 1,
        'from_default' => 'yes',
    ]);

    $context->setDefaults($defaults);

    expect($context->toArray())->toBe([
        'lang' => 'en_US',
        'tz' => 'Asia/Riyadh',
        'company_id' => 10,
        'keep' => 999,
        'local' => true,
        'from_default' => 'yes',
    ]);
});

test('setDefaults ignores null context', function () {
    $context = new Context('en_US', 'UTC', 1, ['key' => 'value']);

    $context->setDefaults(null);

    expect($context->toArray())->toBe([
        'lang' => 'en_US',
        'tz' => 'UTC',
        'company_id' => 1,
        'key' => 'value',
    ]);
});

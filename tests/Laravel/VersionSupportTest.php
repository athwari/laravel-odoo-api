<?php

namespace Athwari\LaravelOdooApi\Tests\Laravel;

use Athwari\LaravelOdooApi\Facades\Odoo;

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

it('supports features based on version', function () {
    $fake = Odoo::fake();

    // Odoo 17
    $fake->setVersionPayload(['server_version_info' => [17, 0, 0, 'final', 0]]);
    expect(Odoo::supports('jsonrpc_context'))->toBeTrue()
        ->and(Odoo::supports('read_group_groupby'))->toBeTrue();

    // Odoo 14
    $fake->setVersionPayload(['server_version_info' => [14, 0, 0, 'final', 0]]);
    expect(Odoo::supports('jsonrpc_context'))->toBeFalse()
        ->and(Odoo::supports('read_group_groupby'))->toBeTrue();

    // Odoo 10
    $fake->setVersionPayload(['server_version_info' => [10, 0, 0, 'final', 0]]);
    expect(Odoo::supports('jsonrpc_context'))->toBeFalse()
        ->and(Odoo::supports('read_group_groupby'))->toBeFalse();
});

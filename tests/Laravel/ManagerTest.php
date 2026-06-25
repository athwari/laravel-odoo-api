<?php

namespace Athwari\LaravelOdooApi\Tests\Laravel;

use Athwari\LaravelOdooApi\Facades\Odoo as OdooFacade;
use Athwari\LaravelOdooApi\OdooApiServiceProvider;
use Athwari\LaravelOdooApi\OdooManager;
use InvalidArgumentException;

beforeEach(function () {
    $this->app->register(OdooApiServiceProvider::class);
});

test('manager uses root config as fallback for default connection', function () {
    // Set legacy config setup
    config()->set('odoo-api', [
        'database' => 'legacy_db',
        'host' => 'http://legacy',
        'username' => 'legacy_user',
        'password' => 'legacy_pass',
    ]);

    $manager = $this->app->make(OdooManager::class);
    $odoo = $manager->connection('default');

    expect($odoo->getConfig()->getDatabase())->toBe('legacy_db');
    expect($odoo->getConfig()->getHost())->toBe('http://legacy');
});

test('manager resolves named connection', function () {
    config()->set('odoo-api', [
        'default' => 'default',
        'connections' => [
            'default' => [
                'database' => 'db1',
                'host' => 'http://db1',
                'username' => 'user1',
                'password' => 'pass1',
            ],
            'erp' => [
                'database' => 'erp_db',
                'host' => 'http://erp',
                'username' => 'erp_user',
                'password' => 'erp_pass',
            ],
        ],
    ]);

    $manager = $this->app->make(OdooManager::class);
    $erp = $manager->connection('erp');

    expect($erp->getConfig()->getDatabase())->toBe('erp_db');
});

test('manager throws invalid argument exception for unknown connection', function () {
    config()->set('odoo-api', [
        'default' => 'default',
        'connections' => [
            'default' => [
                'database' => 'db1',
                'host' => 'http://db1',
                'username' => 'user1',
                'password' => 'pass1',
            ],
        ],
    ]);

    $manager = $this->app->make(OdooManager::class);
    $manager->connection('unknown');
})->throws(InvalidArgumentException::class);

test('facade resolves default connection and forwards calls', function () {
    config()->set('odoo-api', [
        'default' => 'default',
        'connections' => [
            'default' => [
                'database' => 'db_default',
                'host' => 'http://default',
                'username' => 'user',
                'password' => 'pass',
            ],
        ],
    ]);

    $odoo = OdooFacade::connection();
    expect($odoo->getConfig()->getDatabase())->toBe('db_default');

    // Test forwarding
    expect(OdooFacade::getConfig()->getDatabase())->toBe('db_default');
});

test('di resolves default connection instance directly', function () {
    config()->set('odoo-api', [
        'default' => 'default',
        'connections' => [
            'default' => [
                'database' => 'db_default',
                'host' => 'http://default',
                'username' => 'user',
                'password' => 'pass',
            ],
        ],
    ]);

    $odoo = $this->app->make(\Athwari\LaravelOdooApi\Odoo::class);
    expect($odoo->getConfig()->getDatabase())->toBe('db_default');
});

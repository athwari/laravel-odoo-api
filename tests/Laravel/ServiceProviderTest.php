<?php

namespace Athwari\LaravelOdooApi\Tests\Feature;

use Athwari\LaravelOdooApi\OdooApiServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

test('it logs a warning if essential config is missing on boot', function () {
    Config::set('odoo-api.host', null);
    Config::set('odoo-api.database', null);
    
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'Odoo API configuration is incomplete');
        });

    $provider = new OdooApiServiceProvider(app());
    $provider->boot();
});

test('it does not log a warning if essential config is present', function () {
    Config::set('odoo-api.host', 'https://example.com');
    Config::set('odoo-api.database', 'test_db');
    Config::set('odoo-api.username', 'admin');
    Config::set('odoo-api.password', 'secret');
    
    Log::shouldReceive('warning')->never();

    $provider = new OdooApiServiceProvider(app());
    $provider->boot();
});

test('it does not log a warning if api key is used instead of password', function () {
    Config::set('odoo-api.host', 'https://example.com');
    Config::set('odoo-api.database', 'test_db');
    Config::set('odoo-api.username', 'admin');
    Config::set('odoo-api.password', null);
    Config::set('odoo-api.api_key', 'secret_key');
    
    Log::shouldReceive('warning')->never();

    $provider = new OdooApiServiceProvider(app());
    $provider->boot();
});

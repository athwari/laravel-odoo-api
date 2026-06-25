<?php

namespace Athwari\LaravelOdooApi\Tests\Laravel;

use Athwari\LaravelOdooApi\Odoo;
use Athwari\LaravelOdooApi\Odoo\Config;
use Athwari\LaravelOdooApi\OdooApiServiceProvider;
use Illuminate\Console\Command;
use Mockery;

beforeEach(function () {
    $this->app->register(OdooApiServiceProvider::class);
});

test('odoo:check-config outputs success with valid config using password', function () {
    $odoo = Mockery::mock(Odoo::class);
    $config = new Config('db', 'http://localhost', 'admin', 'pass');
    $odoo->shouldReceive('getConfig')->once()->andReturn($config);
    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:check-config')
        ->expectsOutputToContain('Host configured')
        ->expectsOutputToContain('Database configured')
        ->expectsOutputToContain('Username configured')
        ->expectsOutputToContain('Authentication: Password')
        ->expectsOutputToContain('SSL Verification: Enabled')
        ->expectsOutputToContain('Configuration is complete and well-formed.')
        ->assertExitCode(Command::SUCCESS);
});

test('odoo:check-config outputs success with valid config using api key', function () {
    $odoo = Mockery::mock(Odoo::class);
    $config = new Config('db', 'https://example.com', 'admin', '', 'test_api_key', null, 30, false);
    $odoo->shouldReceive('getConfig')->once()->andReturn($config);
    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:check-config')
        ->expectsOutputToContain('Authentication: API Key')
        ->expectsOutputToContain('SSL Verification: Disabled')
        ->expectsOutputToContain('Configuration is complete and well-formed.')
        ->assertExitCode(Command::SUCCESS);
});

test('odoo:check-config outputs success with valid config using fixed user id', function () {
    $odoo = Mockery::mock(Odoo::class);
    // Even without password/apiKey, it's valid if fixedUserId is set
    $config = new Config('db', 'http://localhost', 'admin', '', '', 1);
    $odoo->shouldReceive('getConfig')->once()->andReturn($config);
    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:check-config')
        ->expectsOutputToContain('Authentication: Bypassed (Fixed User ID)')
        ->expectsOutputToContain('Fixed User ID: 1')
        ->expectsOutputToContain('Configuration is complete and well-formed.')
        ->assertExitCode(Command::SUCCESS);
});

test('odoo:check-config fails and shows errors for missing required fields', function () {
    $odoo = Mockery::mock(Odoo::class);
    $config = new Config('', '', '', '');
    $odoo->shouldReceive('getConfig')->once()->andReturn($config);
    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:check-config')
        ->expectsOutputToContain('Host: Missing')
        ->expectsOutputToContain('Database: Missing')
        ->expectsOutputToContain('Username: Missing')
        ->expectsOutputToContain('Authentication: Missing Password or API Key')
        ->expectsOutputToContain('Configuration validation failed.')
        ->assertExitCode(Command::FAILURE);
});

test('odoo:check-config fails for invalid host format', function () {
    $odoo = Mockery::mock(Odoo::class);
    $config = new Config('db', 'not-a-url', 'admin', 'pass');
    $odoo->shouldReceive('getConfig')->once()->andReturn($config);
    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:check-config')
        ->expectsOutputToContain('Host: Invalid URL format (not-a-url)')
        ->assertExitCode(Command::FAILURE);
});

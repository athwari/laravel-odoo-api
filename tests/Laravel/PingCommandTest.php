<?php

namespace Athwari\LaravelOdooApi\Tests\Laravel;

use Athwari\LaravelOdooApi\Odoo;
use Athwari\LaravelOdooApi\Odoo\Config;
use Athwari\LaravelOdooApi\Odoo\Models\Version;
use Athwari\LaravelOdooApi\OdooApiServiceProvider;
use Mockery;

beforeEach(function () {
    $this->app->register(OdooApiServiceProvider::class);
});

function makeVersion(): Version
{
    $version = new Version();
    $version->serverVersion = '17.0';
    $version->serverVersionInfo = [17, 0, 0, 'final', 0, ''];
    $version->serverSerie = '17.0';
    $version->protocolVersion = 1;

    return $version;
}

test('odoo:ping succeeds with valid credentials', function () {
    $odoo = Mockery::mock(Odoo::class);

    $config = new Config('test_db', 'https://example.odoo.com', 'admin', 'admin');
    $odoo->shouldReceive('getConfig')->andReturn($config);
    $odoo->shouldReceive('version')->once()->andReturn(makeVersion());
    $odoo->shouldReceive('connect')->once()->with(true)->andReturn($odoo);

    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:ping')
        ->expectsOutputToContain('Pinging Odoo server...')
        ->expectsOutputToContain('Host: https://example.odoo.com')
        ->expectsOutputToContain('Database: test_db')
        ->expectsOutputToContain('Username: admin')
        ->expectsOutputToContain('Auth Mode: Password')
        ->expectsOutputToContain('Server Version: 17.0')
        ->expectsOutputToContain('Successfully connected to Odoo!')
        ->assertExitCode(0);
});

test('odoo:ping displays api key auth mode', function () {
    $odoo = Mockery::mock(Odoo::class);

    $config = new Config('test_db', 'https://example.odoo.com', 'admin', 'admin', 'my-api-key');
    $odoo->shouldReceive('getConfig')->andReturn($config);
    $odoo->shouldReceive('version')->once()->andReturn(makeVersion());
    $odoo->shouldReceive('connect')->once()->with(true)->andReturn($odoo);

    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:ping')
        ->expectsOutputToContain('Auth Mode: API Key')
        ->assertExitCode(0);
});

test('odoo:ping displays fixed user auth mode', function () {
    $odoo = Mockery::mock(Odoo::class);

    $config = new Config('test_db', 'https://example.odoo.com', 'admin', 'admin', null, 55);
    $odoo->shouldReceive('getConfig')->andReturn($config);
    $odoo->shouldReceive('version')->once()->andReturn(makeVersion());
    $odoo->shouldReceive('connect')->once()->with(true)->andReturn($odoo);

    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:ping')
        ->expectsOutputToContain('Auth Mode: Fixed User ID (55)')
        ->assertExitCode(0);
});

test('odoo:ping fails when authentication fails', function () {
    $odoo = Mockery::mock(Odoo::class);

    $config = new Config('test_db', 'https://example.odoo.com', 'admin', 'wrong_pass');
    $odoo->shouldReceive('getConfig')->andReturn($config);
    $odoo->shouldReceive('version')->once()->andReturn(makeVersion());
    $odoo->shouldReceive('connect')->once()->with(true)->andThrow(new \Athwari\LaravelOdooApi\Exceptions\AuthenticationException('Access Denied'));

    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:ping')
        ->expectsOutputToContain('Authentication failed: Access Denied')
        ->assertExitCode(1);
});

test('odoo:ping fails when connection fails', function () {
    $odoo = Mockery::mock(Odoo::class);

    $config = new Config('test_db', 'https://example.odoo.com', 'admin', 'admin');
    $odoo->shouldReceive('getConfig')->andReturn($config);
    $odoo->shouldReceive('version')->once()->andThrow(new \Athwari\LaravelOdooApi\Exceptions\ConnectionException('cURL error 28: Timeout'));

    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:ping')
        ->expectsOutputToContain('Connection failed: cURL error 28: Timeout')
        ->assertExitCode(1);
});

<?php

namespace Athwari\LaravelOdooApi\Tests\Laravel;

use Athwari\LaravelOdooApi\Exceptions\ConnectionException;
use Athwari\LaravelOdooApi\Exceptions\RecordNotFoundException;
use Athwari\LaravelOdooApi\Odoo;
use Athwari\LaravelOdooApi\OdooApiServiceProvider;
use Illuminate\Console\Command;
use Mockery;

beforeEach(function () {
    $this->app->register(OdooApiServiceProvider::class);
});

function mockFields(): object
{
    return (object) [
        'name' => [
            'type' => 'char',
            'string' => 'Name',
            'required' => true,
        ],
        'email' => [
            'type' => 'char',
            'string' => 'Email',
            'required' => false,
        ],
        'country_id' => [
            'type' => 'many2one',
            'string' => 'Country',
            'required' => false,
        ],
    ];
}

test('odoo:fields outputs a console table with fields', function () {
    $odoo = Mockery::mock(Odoo::class);
    $odoo->shouldReceive('listModelFields')->once()->with('res.partner')->andReturn(mockFields());
    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:fields', ['model' => 'res.partner'])
        ->expectsTable(['Field', 'Type', 'Required'], [
            ['country_id', 'many2one', 'no'],
            ['email', 'char', 'no'],
            ['name', 'char', 'yes'],
        ])
        ->assertExitCode(Command::SUCCESS);
});

test('odoo:fields outputs valid json when --json is passed', function () {
    $odoo = Mockery::mock(Odoo::class);
    $fields = mockFields();
    $odoo->shouldReceive('listModelFields')->once()->with('res.partner')->andReturn($fields);
    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:fields', ['model' => 'res.partner', '--json' => true])
        ->expectsOutputToContain(json_encode($fields, JSON_PRETTY_PRINT))
        ->assertExitCode(Command::SUCCESS);
});

test('odoo:fields returns failure code when Odoo throws exception', function () {
    $odoo = Mockery::mock(Odoo::class);
    $odoo->shouldReceive('listModelFields')
        ->once()
        ->with('invalid.model')
        ->andThrow(new RecordNotFoundException('Model not found'));

    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:fields', ['model' => 'invalid.model'])
        ->expectsOutputToContain('Model not found')
        ->assertExitCode(Command::FAILURE);
});

test('odoo:fields returns failure code on connection error', function () {
    $odoo = Mockery::mock(Odoo::class);
    $odoo->shouldReceive('listModelFields')
        ->once()
        ->with('res.partner')
        ->andThrow(new ConnectionException('cURL error 28: Timeout'));

    $this->app->instance(Odoo::class, $odoo);

    $this->artisan('odoo:fields', ['model' => 'res.partner'])
        ->expectsOutputToContain('cURL error 28: Timeout')
        ->assertExitCode(Command::FAILURE);
});

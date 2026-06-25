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

class TestDtoWithFromArray
{
    public string $name;

    public static function fromArray(array $data): static
    {
        $dto = new static();
        $dto->name = strtoupper($data['name']);

        return $dto;
    }
}

class TestDtoWithConstructor
{
    public string $name;

    public function __construct(array $data)
    {
        $this->name = strtolower($data['name']);
    }
}

it('maps to DTO using fromArray if available', function () {
    Odoo::fake()
        ->shouldReceive('res.partner', 'search_read')
        ->andReturn([['id' => 1, 'name' => 'John Doe']]);

    $results = Odoo::model('res.partner')->as(TestDtoWithFromArray::class)->get();

    expect($results[0])->toBeInstanceOf(TestDtoWithFromArray::class)
        ->and($results[0]->name)->toBe('JOHN DOE');
});

it('maps to DTO using constructor if fromArray is missing', function () {
    Odoo::fake()
        ->shouldReceive('res.partner', 'search_read')
        ->andReturn([['id' => 1, 'name' => 'John Doe']]);

    $results = Odoo::model('res.partner')->as(TestDtoWithConstructor::class)->get();

    expect($results[0])->toBeInstanceOf(TestDtoWithConstructor::class)
        ->and($results[0]->name)->toBe('john doe');
});

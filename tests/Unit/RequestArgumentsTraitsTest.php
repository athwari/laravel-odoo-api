<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Domain;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasDomain;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasOptions;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Options;

test('HasDomain trait forwards where, whereNot and orWhere calls', function () {
    $builder = new class()
    {
        use HasDomain;

        public function __construct()
        {
            $this->domain = new Domain();
        }

        public function toDomainArray(): array
        {
            return $this->domain->toArray();
        }
    };

    $builder
        ->where('name', '=', 'A')
        ->orWhere('name', '=', 'B')
        ->whereNot('active', '=', false);

    expect($builder->toDomainArray())->toBe([
        '|',
        ['name', '=', 'A'],
        ['name', '=', 'B'],
        '!',
        ['active', '=', false],
    ]);
});

test('HasOptions trait forwards option values', function () {
    $builder = new class()
    {
        use HasOptions;

        public function __construct()
        {
            $this->options = new Options();
        }

        public function toOptionsArray(): array
        {
            return $this->options->toArray();
        }
    };

    $builder
        ->option('active_test', false)
        ->option('limit', 15);

    expect($builder->toOptionsArray())->toBe([
        'active_test' => false,
        'limit' => 15,
    ]);
});

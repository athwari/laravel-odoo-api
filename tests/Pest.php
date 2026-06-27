<?php

declare(strict_types=1);
use Athwari\LaravelOdooApi\Tests\Integration\IntegrationTestCase;
use Orchestra\Testbench\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide here will be executed before every test. You can
| use this file to bind test cases, traits, and other helper classes to
| your tests.
|
*/

uses(Athwari\LaravelOdooApi\Tests\TestCase::class)->in('Unit');
uses(IntegrationTestCase::class)->in('Feature', 'Integration');
uses(TestCase::class)->in('Laravel');

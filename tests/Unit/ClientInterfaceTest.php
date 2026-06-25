<?php

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\Contracts\OdooClientInterface;
use Athwari\LaravelOdooApi\JsonRpc\Client;
use Athwari\LaravelOdooApi\Testing\OdooFake;

it('JsonRpc Client implements OdooClientInterface', function () {
    $client = new Client('http://localhost', 'common');
    expect($client instanceof OdooClientInterface)->toBeTrue();
});

it('OdooFake implements OdooClientInterface', function () {
    $client = new OdooFake();
    expect($client instanceof OdooClientInterface)->toBeTrue();
});

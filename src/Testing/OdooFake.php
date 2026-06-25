<?php

namespace Athwari\LaravelOdooApi\Testing;

use Athwari\LaravelOdooApi\Contracts\OdooClientInterface;
use PHPUnit\Framework\Assert;

class OdooFake implements OdooClientInterface
{
    private array $expectations = [];

    private array $recorded = [];

    private array $versionPayload = [
        'server_version' => '17.0',
        'server_version_info' => [17, 0, 0, 'final', 0],
        'server_serie' => '17.0',
        'protocol_version' => 1,
    ];

    public function setVersionPayload(array $payload): static
    {
        $this->versionPayload = $payload;

        return $this;
    }

    public function shouldReceive(string $model, string $method): FakeExpectation
    {
        $expectation = new FakeExpectation($model, $method);
        $this->expectations[] = $expectation;

        return $expectation;
    }

    public function authenticate(string $db, string $username, string $password, array $options = []): int
    {
        return 1;
    }

    public function version(): array
    {
        return $this->versionPayload;
    }

    public function execute_kw(string $db, int $uid, string $password, string $model, string $method, array $args = [], array $options = []): mixed
    {
        $this->recorded[] = compact('model', 'method', 'args', 'options');

        foreach ($this->expectations as $expectation) {
            if ($expectation->matches($model, $method)) {
                return $expectation->resolve();
            }
        }

        throw new \RuntimeException("No mock response provided for $model@$method in OdooFake");
    }

    public function assertSent(callable $callback): void
    {
        $passed = false;
        foreach ($this->recorded as $record) {
            if ($callback($record['model'], $record['method'], $record['args'], $record['options'])) {
                $passed = true;
                break;
            }
        }

        Assert::assertTrue($passed, 'An expected Odoo RPC call was not sent.');
    }

    public function assertNotSent(callable $callback): void
    {
        $passed = true;
        foreach ($this->recorded as $record) {
            if ($callback($record['model'], $record['method'], $record['args'], $record['options'])) {
                $passed = false;
                break;
            }
        }

        Assert::assertTrue($passed, 'An unexpected Odoo RPC call was sent.');
    }
}

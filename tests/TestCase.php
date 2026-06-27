<?php

namespace Athwari\LaravelOdooApi\Tests;

use Athwari\LaravelOdooApi\Odoo\Casts\CastHandler;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for package unit tests.
 *
 * Tests are fully offline: no network calls are made anywhere in this
 * suite. HTTP responses are stubbed via Guzzle's MockHandler and
 * injected directly into the classes under test.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(Container::class)) {
            $container = Container::getInstance();
            if ($container && ! $container->bound('events')) {
                $container->singleton('events', function () {
                    return new class()
                    {
                        public function dispatch() {}
                    };
                });
            }
        }
    }

    protected function tearDown(): void
    {
        // Cast registration is process-global static state; reset it
        // between tests so cast-related tests don't leak into others.
        CastHandler::reset();

        parent::tearDown();
    }

    /**
     * Build a mock Guzzle HTTP client that returns the given queued
     * responses in order, regardless of what request is made.
     */
    protected function mockHttpClient(array $responses): HttpClient
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        return new HttpClient(['handler' => $handlerStack]);
    }

    /**
     * Build a single mock JSON-RPC success response.
     */
    protected function jsonRpcResult(mixed $result, int $status = 200): Response
    {
        return new Response($status, [], json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => $result,
        ]));
    }

    /**
     * Build a single mock JSON-RPC error response.
     */
    protected function jsonRpcError(string $message, int $code = 200, array $data = [], int $status = 200): Response
    {
        return new Response($status, [], json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => $code,
                'message' => $message,
                'data' => $data,
            ],
        ]));
    }
}

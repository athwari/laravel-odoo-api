<?php

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\Exceptions\ConnectionException;
use Athwari\LaravelOdooApi\Exceptions\OdooException;
use Athwari\LaravelOdooApi\JsonRpc\Client;
use GuzzleHttp\Psr7\Response;

test('it returns the result on a successful call', function () {
    $httpClient = $this->mockHttpClient([
        $this->jsonRpcResult(['id' => 1, 'name' => 'Test Partner']),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    $result = $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'read', [[1]]);

    expect($result)->toBe(['id' => 1, 'name' => 'Test Partner']);
});

test('it parses a jsonrpc error envelope on http 200', function () {
    $httpClient = $this->mockHttpClient([
        $this->jsonRpcError('Access Denied', 200, ['message' => 'You are not allowed to access this document.']),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    expect(fn () => $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'read', [[1]]))
        ->toThrow(OdooException::class, 'Access Denied');
});

test('it parses a jsonrpc error envelope even on non 200 http status', function () {
    // Odoo (and JSON-RPC servers generally) can return a non-2xx
    // status alongside a perfectly parseable error envelope; the
    // error must still be extracted rather than discarded.
    $httpClient = $this->mockHttpClient([
        $this->jsonRpcError('Internal Server Error', 500, ['message' => 'Something broke'], 500),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    try {
        $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'read', [[1]]);
        $this->fail('Expected an OdooException to be thrown.');
    } catch (OdooException $e) {
        expect($e->getMessage())->toContain('Internal Server Error');
        expect($e->getMessage())->toContain('Something broke');
    }
});

test('it carries structured fault data', function () {
    $httpClient = $this->mockHttpClient([
        $this->jsonRpcError('Validation Error', 200, [
            'message' => 'Invalid field value',
            'debug' => 'Traceback (most recent call last)...',
        ]),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    try {
        $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'write', [[1], ['name' => '']]);
        $this->fail('Expected an OdooException to be thrown.');
    } catch (OdooException $e) {
        $faultData = $e->getFaultData();
        expect($faultData['message'])->toBe('Invalid field value');
        expect($faultData)->toHaveKey('debug');
    }
});

test('it truncates long debug tracebacks', function () {
    $longTraceback = str_repeat('x', 2000);

    $httpClient = $this->mockHttpClient([
        $this->jsonRpcError('Server Error', 200, ['debug' => $longTraceback]),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    try {
        $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'read', [[1]]);
        $this->fail('Expected an OdooException to be thrown.');
    } catch (OdooException $e) {
        // Message should contain the truncated debug text, not the full 2000 chars.
        expect(strlen($e->getMessage()))->toBeLessThan(2000);
    }
});

test('it throws a connection exception on malformed json', function () {
    $httpClient = $this->mockHttpClient([
        new Response(200, [], 'this is not json {{{'),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    expect(fn () => $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'read', [[1]]))
        ->toThrow(ConnectionException::class);
});

test('it throws a connection exception on non 200 with no error envelope', function () {
    $httpClient = $this->mockHttpClient([
        new Response(503, [], json_encode(['jsonrpc' => '2.0', 'id' => 1])),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    expect(fn () => $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'read', [[1]]))
        ->toThrow(ConnectionException::class);
});

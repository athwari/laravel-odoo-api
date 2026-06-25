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
        // Message should NOT contain the debug text to prevent log spam.
        expect($e->getMessage())->not->toContain('x');
        // The fault data should contain the truncated debug text.
        expect(strlen($e->getFaultData()['debug']))->toBe(500);
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
        // Use 400 so it doesn't trigger retry logic (which retries 502/503/504)
        new Response(400, [], json_encode(['jsonrpc' => '2.0', 'id' => 1])),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    expect(fn () => $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'read', [[1]]))
        ->toThrow(ConnectionException::class);
});

test('it parses AccessError into AccessDeniedException', function () {
    $httpClient = $this->mockHttpClient([
        $this->jsonRpcError('Odoo Server Error', 200, ['name' => 'odoo.exceptions.AccessError', 'message' => 'Deny']),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    expect(fn () => $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'read', [[1]]))
        ->toThrow(\Athwari\LaravelOdooApi\Exceptions\AccessDeniedException::class);
});

test('it parses MissingError into RecordNotFoundException', function () {
    $httpClient = $this->mockHttpClient([
        $this->jsonRpcError('Odoo Server Error', 200, ['name' => 'odoo.exceptions.MissingError', 'message' => 'Missing']),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    expect(fn () => $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'read', [[1]]))
        ->toThrow(\Athwari\LaravelOdooApi\Exceptions\RecordNotFoundException::class);
});

test('it parses ValidationError into ValidationException', function () {
    $httpClient = $this->mockHttpClient([
        $this->jsonRpcError('Odoo Server Error', 200, ['name' => 'odoo.exceptions.ValidationError', 'message' => 'Invalid']),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    expect(fn () => $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'read', [[1]]))
        ->toThrow(\Athwari\LaravelOdooApi\Exceptions\ValidationException::class);
});

test('it retries on a concurrent update exception and succeeds', function () {
    $httpClient = $this->mockHttpClient([
        $this->jsonRpcError('Odoo Server Error', 200, [
            'name' => 'odoo.exceptions.UserError',
            'message' => 'A concurrent update has occurred',
            'debug' => 'serializationfailure',
        ]),
        $this->jsonRpcResult(['id' => 1, 'name' => 'Test Partner']),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    $result = $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'write', [[1]]);

    expect($result)->toBe(['id' => 1, 'name' => 'Test Partner']);
});

test('it exhausts max retries and throws the original exception', function () {
    $httpClient = $this->mockHttpClient([
        new Response(503, [], json_encode(['jsonrpc' => '2.0', 'id' => 1])),
        new Response(503, [], json_encode(['jsonrpc' => '2.0', 'id' => 1])),
        new Response(503, [], json_encode(['jsonrpc' => '2.0', 'id' => 1])),
        new Response(503, [], json_encode(['jsonrpc' => '2.0', 'id' => 1])),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    expect(fn () => $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'read', [[1]]))
        ->toThrow(ConnectionException::class, 'Failed to connect to Odoo');
});

test('it does not retry validation errors', function () {
    $httpClient = $this->mockHttpClient([
        $this->jsonRpcError('Odoo Server Error', 200, [
            'name' => 'odoo.exceptions.ValidationError',
            'message' => 'Invalid email address',
        ]),
        // If it retries, it would consume this second response.
        // We want to ensure it throws immediately without retrying.
        $this->jsonRpcResult(['id' => 1]),
    ]);

    $client = new Client('https://example.odoo.com', 'object', 30, true, $httpClient);

    expect(fn () => $client->execute_kw('test_db', 1, 'secret', 'res.partner', 'write', [[1]]))
        ->toThrow(\Athwari\LaravelOdooApi\Exceptions\ValidationException::class, 'Invalid email address');
});

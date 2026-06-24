<?php

namespace Athwari\LaravelOdooApi\JsonRpc;

use Athwari\LaravelOdooApi\Exceptions\ConnectionException;
use Athwari\LaravelOdooApi\Exceptions\OdooException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Low-level JSON-RPC 2.0 transport for Odoo.
 *
 * Builds compliant JSON-RPC payloads and interprets responses. Kept
 * separate from the higher-level Odoo/Endpoint classes so the transport
 * can be unit-tested in isolation by injecting a mock HttpClient.
 *
 * Error handling notes:
 *  - The JSON-RPC "error" envelope is parsed regardless of HTTP status
 *    code. Odoo (and JSON-RPC generally) commonly returns HTTP 200 with
 *    an error envelope in the body, but a non-2xx status does not mean
 *    the body isn't still meaningful JSON-RPC, so both paths are handled
 *    the same way.
 *  - Malformed/non-JSON response bodies raise a ConnectionException
 *    rather than failing silently or producing a confusing downstream
 *    "undefined property" error.
 *  - Long debug tracebacks from error.data.debug are truncated before
 *    being embedded in the exception message.
 */
/**
 * @method mixed authenticate(string $db, string $username, string $password, array $options = [])
 * @method mixed version()
 * @method mixed execute_kw(string $db, int $uid, string $password, string $model, string $method, array $args = [], array $options = [])
 */
class Client
{
    private const DEBUG_TRUNCATE_LENGTH = 500;

    private HttpClient $httpClient;

    private ?ResponseInterface $lastResponse = null;

    private int $requestId = 0;

    /**
     * @param  string  $baseUri  Base URL of the Odoo instance (e.g. https://mycompany.odoo.com)
     * @param  string  $service  JSON-RPC service name ('object' or 'common')
     * @param  int  $timeout  Request timeout in seconds
     * @param  bool  $sslVerify  Verify the server TLS certificate. Set false only for
     *                           self-signed certs in local/dev; never disable in production.
     * @param  HttpClient|null  $httpClient  Injectable Guzzle client, primarily for testing
     */
    public function __construct(
        string $baseUri,
        private readonly string $service = 'object',
        private readonly int $timeout = 30,
        private readonly bool $sslVerify = true,
        ?HttpClient $httpClient = null,
    ) {
        if ($httpClient instanceof \GuzzleHttp\Client) {
            $this->httpClient = $httpClient;

            return;
        }

        if (trim($baseUri) === '') {
            throw new ConnectionException(
                'Odoo host URL is empty. '
                .'Set ODOO_HOST in your environment or the "host" key in config/odoo-api.php.',
            );
        }

        $this->httpClient = new HttpClient([
            'base_uri' => rtrim($baseUri, '/').'/',
            'timeout' => $this->timeout,
            'verify' => $this->sslVerify,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Magic call: maps any method name to a JSON-RPC "call" on the
     * configured service, e.g. $client->authenticate(...) calls the
     * 'authenticate' method on the 'common' service.
     *
     * @throws ConnectionException On HTTP/network/malformed-response failures
     * @throws OdooException On a JSON-RPC error envelope from Odoo
     */
    public function __call(string $method, array $arguments): mixed
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'id' => ++$this->requestId,
            'params' => [
                'service' => $this->service,
                'method' => $method,
                'args' => $arguments,
            ],
        ];

        try {
            $response = $this->httpClient->post('jsonrpc', [
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new ConnectionException(
                "Failed to connect to Odoo: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
            );
        }

        $this->lastResponse = $response;

        return $this->parseResponse($response);
    }

    public function lastResponse(): ?ResponseInterface
    {
        return $this->lastResponse;
    }

    /**
     * Parse a JSON-RPC response body regardless of HTTP status code.
     *
     * @throws ConnectionException On malformed/non-JSON bodies
     * @throws OdooException On a JSON-RPC error envelope
     */
    private function parseResponse(ResponseInterface $response): mixed
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ConnectionException(
                'Invalid JSON response from Odoo: '.json_last_error_msg(),
                $response->getStatusCode(),
            );
        }

        if (isset($decoded['error'])) {
            throw $this->buildOdooException($decoded['error']);
        }

        if ($response->getStatusCode() >= 400) {
            // No JSON-RPC error envelope, but the transport itself failed.
            throw new ConnectionException(
                "Odoo returned HTTP {$response->getStatusCode()} with no JSON-RPC error envelope.",
                $response->getStatusCode(),
            );
        }

        return $decoded['result'] ?? null;
    }

    private function buildOdooException(array $error): OdooException
    {
        $message = $error['message'] ?? 'Odoo Exception';
        $data = $error['data'] ?? [];

        if (isset($data['message'])) {
            $message .= ': '.$data['message'];
        }

        if (isset($data['debug'])) {
            $debug = substr((string) $data['debug'], 0, self::DEBUG_TRUNCATE_LENGTH);
            $message .= "\nDebug: ".$debug;
        }

        return new OdooException(
            $message,
            (int) ($error['code'] ?? 0),
            null,
            $data,
        );
    }
}

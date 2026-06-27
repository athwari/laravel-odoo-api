<?php

namespace Athwari\LaravelOdooApi\JsonRpc;

use Athwari\LaravelOdooApi\Contracts\OdooClientInterface;
use Athwari\LaravelOdooApi\Exceptions\AccessDeniedException;
use Athwari\LaravelOdooApi\Exceptions\ConnectionException;
use Athwari\LaravelOdooApi\Exceptions\OdooException;
use Athwari\LaravelOdooApi\Exceptions\RecordNotFoundException;
use Athwari\LaravelOdooApi\Exceptions\ValidationException;
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
class Client implements OdooClientInterface
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
        if ($httpClient instanceof HttpClient) {
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
        return $this->callRpc($method, $arguments);
    }

    public function authenticate(string $db, string $username, string $password, array $options = []): int
    {
        return $this->callRpc('authenticate', [$db, $username, $password, $options]);
    }

    public function version(): array
    {
        return (array) $this->callRpc('version', []);
    }

    public function execute_kw(string $db, int $uid, string $password, string $model, string $method, array $args = [], array $options = []): mixed
    {
        return $this->callRpc('execute_kw', [$db, $uid, $password, $model, $method, $args, $options]);
    }

    /**
     * Executes the actual JSON-RPC payload building and network call.
     */
    private function callRpc(string $method, array $arguments): mixed
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

        $maxRetries = 3;
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
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
            } catch (\Throwable $e) {
                if ($attempt > $maxRetries || ! $this->shouldRetry($e)) {
                    throw $e;
                }

                // Sleep with simple exponential backoff: 200ms, 400ms, 800ms
                usleep((int) (200000 * (2 ** ($attempt - 1))));
            }
        }
    }

    private function shouldRetry(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            $message = strtolower($e->getMessage());

            if (str_contains($message, 'timeout') || str_contains($message, 'connection refused') || str_contains($message, 'curl error 28')) {
                return true;
            }

            $code = $e->getCode();
            if (in_array($code, [502, 503, 504], true)) {
                return true;
            }
        }

        if ($e instanceof OdooException) {
            $message = strtolower($e->getMessage());
            $debug = strtolower((string) ($e->getFaultData()['debug'] ?? ''));

            return str_contains($message, 'concurrent update')
                || str_contains($debug, 'concurrent update')
                || str_contains($debug, 'serializationfailure')
                || str_contains($message, 'deadlock detected')
                || str_contains($debug, 'deadlock detected');
        }

        return false;
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
            // Truncate the debug traceback to prevent memory bloat.
            // We DO NOT append this to $message to prevent massive log spam
            // and potential SQL leakage in the host Laravel application's logs.
            $data['debug'] = substr((string) $data['debug'], 0, self::DEBUG_TRUNCATE_LENGTH);
        }

        $exceptionClass = OdooException::class;

        if (isset($data['name'])) {
            $exceptionClass = match ($data['name']) {
                'odoo.exceptions.AccessError' => AccessDeniedException::class,
                'odoo.exceptions.MissingError' => RecordNotFoundException::class,
                'odoo.exceptions.ValidationError' => ValidationException::class,
                default => OdooException::class,
            };
        }

        return new $exceptionClass(
            $message,
            (int) ($error['code'] ?? 0),
            null,
            $data,
        );
    }
}

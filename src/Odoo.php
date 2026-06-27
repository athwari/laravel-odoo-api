<?php

namespace Athwari\LaravelOdooApi;

use Athwari\LaravelOdooApi\Exceptions\AuthenticationException;
use Athwari\LaravelOdooApi\Odoo\Config;
use Athwari\LaravelOdooApi\Odoo\Context;
use Athwari\LaravelOdooApi\Odoo\Endpoint\CommonEndpoint;
use Athwari\LaravelOdooApi\Odoo\Endpoint\ObjectEndpoint;
use Athwari\LaravelOdooApi\Odoo\Models\Version;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Domain;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Options;
use Athwari\LaravelOdooApi\Odoo\Request\Request;
use Athwari\LaravelOdooApi\Odoo\Request\RequestBuilder;
use Athwari\LaravelOdooApi\Testing\OdooFake;

/**
 * Main entry point for talking to an Odoo instance over JSON-RPC.
 *
 * Typical usage:
 *   $odoo = new Odoo(new Config($database, $host, $username, $password));
 *   $odoo->connect();
 *   $partners = $odoo->model('res.partner')->where('active', '=', true)->get();
 */
class Odoo
{
    private readonly CommonEndpoint $common;

    private ?ObjectEndpoint $object = null;

    private ?int $uid = null;

    private ?OdooFake $fakeClient = null;

    public function __construct(
        private readonly Config $config,
        private Context $context = new Context(),
    ) {
        $this->common = new CommonEndpoint($this->config);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getCommonEndpoint(): CommonEndpoint
    {
        return $this->common;
    }

    public function setFakeClient(OdooFake $fake): static
    {
        $this->fakeClient = $fake;

        if ($this->object) {
            $this->object->setClient($fake);
        }

        return $this;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function setContext(Context $context): static
    {
        $this->context = $context;
        $this->object?->setContext($context);

        return $this;
    }

    /**
     * Authenticate with Odoo and prepare the object endpoint for
     * subsequent calls. Safe to call more than once; subsequent calls
     * are a no-op unless $force is true.
     *
     * @throws AuthenticationException
     */
    public function connect(bool $force = false): static
    {
        if ($this->uid !== null && ! $force) {
            return $this;
        }

        $this->uid = $this->common->authenticate();
        $this->object = new ObjectEndpoint($this->config, $this->context, $this->uid);

        if ($this->fakeClient) {
            $this->object->setClient($this->fakeClient);
        }

        return $this;
    }

    public function isConnected(): bool
    {
        return $this->uid !== null;
    }

    /**
     * Inject a pre-built ObjectEndpoint, bypassing connect()/authenticate()
     * entirely. Primarily useful for tests: build an ObjectEndpoint with a
     * mocked JsonRpc\Client, then call this instead of connect() so no
     * real network call is made.
     */
    public function setObjectEndpoint(ObjectEndpoint $endpoint, int $uid): static
    {
        $this->object = $endpoint;
        $this->uid = $uid;

        return $this;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function version(): Version
    {
        return $this->common->version();
    }

    /**
     * Check if the connected Odoo instance supports a specific feature.
     */
    public function supports(string $feature): bool
    {
        $majorVersion = $this->version()->serverVersionInfo[0] ?? 0;

        return match ($feature) {
            'jsonrpc_context' => $majorVersion >= 15,
            'read_group_groupby' => $majorVersion >= 12,
            default => false,
        };
    }

    private function object(): ObjectEndpoint
    {
        if (! $this->object instanceof ObjectEndpoint) {
            $this->connect();
        }

        return $this->object;
    }

    public function model(string $model, ?Domain $domain = null): RequestBuilder
    {
        return $this->object()->model($model, $domain);
    }

    public function execute(Request $request, ?Options $options = null): mixed
    {
        return $this->object()->execute($request, $options);
    }

    /**
     * Call an arbitrary Odoo model method via execute_kw. Use this for
     * custom/non-standard methods that don't have a dedicated typed
     * method below (e.g. action_confirm, or other business methods).
     */
    public function executeKw(string $model, string $method, array $args = [], array|Options $kwargs = []): mixed
    {
        if ($kwargs instanceof Options) {
            $kwargs = $kwargs->toArray();
        }

        return $this->object()->executeKw($model, $method, $args, $kwargs);
    }

    public function checkAccessRights(string $model, string $permission = 'read'): bool
    {
        return $this->object()->checkAccessRights($model, $permission);
    }

    public function can(string $model, string $permission = 'read'): bool
    {
        return $this->checkAccessRights($model, $permission);
    }

    public function search(string $model, ?Domain $domain = null, int $offset = 0, ?int $limit = null, ?string $order = null): array
    {
        return $this->object()->search($model, $domain, $offset, $limit, $order);
    }

    public function count(string $model, ?Domain $domain = null): int
    {
        return $this->object()->count($model, $domain);
    }

    public function read(string $model, array $ids, array $fields = []): array
    {
        return $this->object()->read($model, $ids, $fields);
    }

    public function find(string $model, int $id, array $fields = []): ?object
    {
        $result = $this->read($model, [$id], $fields)[0] ?? null;

        return $result ? (object) $result : null;
    }

    public function searchRead(string $model, ?Domain $domain = null, ?array $fields = null, int $offset = 0, ?int $limit = null, ?string $order = null): array
    {
        return $this->object()->searchRead($model, $domain, $fields, $offset, $limit, $order);
    }

    public function readGroup(string $model, array $groupBy, ?Domain $domain = null, ?array $fields = null, int $offset = 0, ?int $limit = null, ?string $order = null): array
    {
        return $this->object()->readGroup($model, $groupBy, $domain, $fields, $offset, $limit, $order);
    }

    public function fieldsGet(string $model, ?array $fields = null, ?array $attributes = null): object
    {
        return $this->object()->fieldsGet($model, $fields, $attributes);
    }

    public function listModelFields(string $model, ?array $fields = null): object
    {
        return $this->fieldsGet($model, $fields);
    }

    public function create(string $model, array $values): bool|int
    {
        return $this->object()->create($model, $values);
    }

    public function write(string $model, array $ids, array $values): bool
    {
        return $this->object()->write($model, $ids, $values);
    }

    public function updateById(string $model, int $id, array $values): bool
    {
        return $this->write($model, [$id], $values);
    }

    public function unlink(string $model, array $ids): bool
    {
        return $this->object()->unlink($model, $ids);
    }

    public function deleteById(string $model, int $id): bool
    {
        return $this->unlink($model, [$id]);
    }
}

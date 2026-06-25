<?php

namespace Athwari\LaravelOdooApi;

use Athwari\LaravelOdooApi\Odoo\Config;
use Athwari\LaravelOdooApi\Odoo\Context;
use Athwari\LaravelOdooApi\Testing\OdooFake;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

class OdooManager
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * The active connection instances.
     *
     * @var array<string, \Athwari\LaravelOdooApi\Odoo>
     */
    protected $connections = [];

    /**
     * The fake client instance, if any.
     */
    protected ?OdooFake $fake = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Replace the underlying transport with a test double.
     */
    public function fake(): OdooFake
    {
        return $this->fake = new OdooFake();
    }

    /**
     * Get an Odoo connection instance.
     */
    public function connection(?string $name = null): Odoo
    {
        $name = $name ?: $this->getDefaultConnection();

        if (! isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Make the Odoo connection instance.
     *
     *
     * @throws \InvalidArgumentException
     */
    protected function makeConnection(string $name): Odoo
    {
        $config = $this->configuration($name);

        $odooConfig = new Config(
            database: (string) ($config['database'] ?? ''),
            host: (string) ($config['host'] ?? ''),
            username: (string) ($config['username'] ?? ''),
            password: (string) ($config['password'] ?? ''),
            apiKey: $config['api_key'] ?? null,
            fixedUserId: isset($config['fixed_user_id']) ? (int) $config['fixed_user_id'] : null,
            timeout: (int) ($config['timeout'] ?? 30),
            sslVerify: (bool) ($config['ssl_verify'] ?? true),
        );

        $contextConfig = $config['context'] ?? [];
        $context = new Context(
            lang: (string) ($contextConfig['lang'] ?? ''),
            timezone: (string) ($contextConfig['timezone'] ?? ''),
            companyId: isset($contextConfig['company_id']) ? (int) $contextConfig['company_id'] : null,
        );

        $odoo = new Odoo($odooConfig, $context);

        if ($this->fake) {
            $odoo->getCommonEndpoint()->setClient($this->fake);
            $odoo->setFakeClient($this->fake);
        }

        return $odoo;
    }

    /**
     * Get the configuration for a connection.
     *
     *
     * @throws \InvalidArgumentException
     */
    protected function configuration(string $name): array
    {
        $name = $name ?: $this->getDefaultConnection();

        // To maintain strict v1 backwards compatibility,
        // if the connection is 'default' and the connections.default array doesn't exist,
        // we fallback to the top-level keys in odoo-api config.
        $connections = $this->app['config']->get('odoo-api.connections');

        if (is_null($connections) || ! is_array($connections)) {
            if ($name === 'default' || $name === $this->getDefaultConnection()) {
                return $this->app['config']->get('odoo-api');
            }
            throw new InvalidArgumentException("Odoo connection [{$name}] not configured.");
        }

        $config = $connections[$name] ?? null;

        if (is_null($config)) {
            // Further fallback if connections array exists but 'default' is not defined inside it.
            if ($name === 'default' || $name === $this->getDefaultConnection()) {
                return $this->app['config']->get('odoo-api');
            }
            throw new InvalidArgumentException("Odoo connection [{$name}] not configured.");
        }

        return $config;
    }

    /**
     * Get the default connection name.
     */
    public function getDefaultConnection(): string
    {
        return $this->app['config']->get('odoo-api.default') ?: 'default';
    }

    /**
     * Set the default connection name.
     */
    public function setDefaultConnection(string $name): void
    {
        $this->app['config']->set('odoo-api.default', $name);
    }

    /**
     * Dynamically pass methods to the default connection.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->connection()->$method(...$parameters);
    }
}

<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Endpoint;

use Athwari\LaravelOdooApi\JsonRpc\Client;
use Athwari\LaravelOdooApi\Odoo\Config;

abstract class Endpoint
{
    protected string $service;

    private ?Client $client = null;

    public function __construct(private readonly Config $config) {}

    public function getClient(bool $fresh = false): Client
    {
        if ($fresh || ! $this->client instanceof \Athwari\LaravelOdooApi\JsonRpc\Client) {
            $config = $this->getConfig();
            $this->client = new Client(
                $config->getHost(),
                $this->service,
                $config->getTimeout(),
                $config->isSslVerify(),
            );
        }

        return $this->client;
    }

    /**
     * Inject a pre-built Client, bypassing lazy construction. Primarily
     * useful for tests that need to stub the underlying HTTP transport.
     */
    public function setClient(Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}

<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Request;

use Athwari\LaravelOdooApi\JsonRpc\Client;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Options;

abstract class Request
{
    public function __construct(
        protected string $model,
        protected string $method,
    ) {}

    abstract public function toArray(): array;

    public function execute(
        Client $client,
        string $database,
        int $uid,
        string $credential,
        Options $options,
    ): mixed {
        return $client->execute_kw(
            $database,
            $uid,
            $credential,
            $this->model,
            $this->method,
            $this->toArray(),
            $options->toArray(),
        );
    }
}

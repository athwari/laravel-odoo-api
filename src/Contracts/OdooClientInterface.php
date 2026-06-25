<?php

namespace Athwari\LaravelOdooApi\Contracts;

interface OdooClientInterface
{
    /**
     * Authenticate with Odoo.
     *
     * @return int The authenticated user ID
     */
    public function authenticate(string $db, string $username, string $password, array $options = []): int;

    /**
     * Get the Odoo server version.
     */
    public function version(): array;

    /**
     * Execute an arbitrary Odoo method.
     */
    public function execute_kw(string $db, int $uid, string $password, string $model, string $method, array $args = [], array $options = []): mixed;
}

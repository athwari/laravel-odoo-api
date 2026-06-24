<?php

namespace Athwari\LaravelOdooApi\Odoo;

/**
 * Immutable connection configuration for an Odoo instance.
 *
 * Supports two authentication modes:
 *  - Password: standard username/password login via common.authenticate
 *  - API key (Odoo 14+): pass an API key instead of a password; it takes
 *    precedence over the password for both authentication and execute_kw
 *    calls when present.
 *
 * A fixedUserId can also be supplied to skip the authentication RPC call
 * entirely in environments where the UID is already known and static
 * (e.g. Docker, CI, or a dedicated integration user).
 */
final class Config
{
    public function __construct(
        private readonly string $database,
        private readonly string $host,
        private readonly string $username,
        private readonly string $password,
        private readonly ?string $apiKey = null,
        private readonly ?int $fixedUserId = null,
        private readonly int $timeout = 30,
        private readonly bool $sslVerify = true,
    ) {}

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function getFixedUserId(): ?int
    {
        return $this->fixedUserId;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function isSslVerify(): bool
    {
        return $this->sslVerify;
    }

    public function isUsingApiKey(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    public function hasFixedUserId(): bool
    {
        return $this->fixedUserId !== null && $this->fixedUserId > 0;
    }

    /**
     * The effective credential used for authentication and execute_kw calls:
     * the API key when configured, otherwise the password.
     */
    public function getCredential(): string
    {
        return $this->isUsingApiKey() ? $this->apiKey : $this->password;
    }
}

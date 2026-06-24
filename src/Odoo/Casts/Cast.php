<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Casts;

/**
 * @template T
 */
abstract class Cast
{
    public const WILDCARD = '*';

    public function applies(mixed $value): bool
    {
        return true;
    }

    public function handlesNullValues(): bool
    {
        return true;
    }

    abstract public function getType(): string;

    /**
     * @return T
     */
    abstract public function cast(mixed $raw): mixed;

    /**
     * @param  T  $value
     */
    abstract public function uncast(mixed $value): mixed;
}

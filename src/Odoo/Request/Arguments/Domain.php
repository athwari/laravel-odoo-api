<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Request\Arguments;

use RuntimeException;

/**
 * Builds Odoo search domain filter arrays.
 *
 * Odoo uses Polish-notation for logical operators: a '|' (or) token is
 * inserted before the pair of leaf conditions it joins; AND is implicit
 * between consecutive leaves and needs no explicit token.
 *
 * Note: this builder supports a flat AND/OR chain only. It cannot express
 * nested boolean groups such as (A OR B) AND (C OR D) — if you need that,
 * build the raw domain array by hand and pass it where a Domain is
 * expected, or compose multiple queries.
 */
class Domain
{
    protected array $conditions = [];

    public function where(string $field, string $operator, mixed $value): static
    {
        $this->conditions[] = [$field, $operator, $value];

        return $this;
    }

    public function whereNot(string $field, string $operator, mixed $value): static
    {
        $this->conditions[] = '!';
        $this->conditions[] = [$field, $operator, $value];

        return $this;
    }

    public function orWhere(string $field, string $operator, mixed $value): static
    {
        if ($this->isEmpty()) {
            throw new RuntimeException('orWhere() cannot be the first condition in a Domain.');
        }

        $this->conditions = [
            ...array_slice($this->conditions, 0, -1),
            '|',
            ...array_slice($this->conditions, -1, 1),
            [$field, $operator, $value],
        ];

        return $this;
    }

    /**
     * Add a raw domain criterion as an escape hatch, e.g. a pre-built
     * triple or a nested sub-expression array.
     */
    public function addRaw(array $criterion): static
    {
        $this->conditions[] = $criterion;

        return $this;
    }

    public function count(): int
    {
        return count($this->conditions);
    }

    public function isEmpty(): bool
    {
        return $this->conditions === [];
    }

    public function toArray(): array
    {
        return $this->conditions;
    }

    public static function make(): static
    {
        $ref = new \ReflectionClass(static::class);

        return $ref->newInstance();
    }
}

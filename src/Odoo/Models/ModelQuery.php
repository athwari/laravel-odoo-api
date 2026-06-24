<?php

namespace Athwari\LaravelOdooApi\Odoo\Models;

use Athwari\LaravelOdooApi\Odoo\OdooModel;
use Athwari\LaravelOdooApi\Odoo\Request\RequestBuilder;

/**
 * Wraps a RequestBuilder so its terminal methods return hydrated
 * OdooModel instances instead of raw stdClass objects.
 *
 * @template T of OdooModel
 */
final class ModelQuery
{
    public function __construct(
        private readonly OdooModel $prototype,
        private readonly RequestBuilder $builder,
    ) {}

    public function where(string $field, string $operator, mixed $value): static
    {
        $this->builder->where($field, $operator, $value);

        return $this;
    }

    public function orWhere(string $field, string $operator, mixed $value): static
    {
        $this->builder->orWhere($field, $operator, $value);

        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): static
    {
        $this->builder->orderBy($field, $direction);

        return $this;
    }

    public function limit(?int $limit): static
    {
        $this->builder->limit($limit);

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->builder->offset($offset);

        return $this;
    }

    public function fields(array $fields): static
    {
        $this->builder->fields($fields);

        return $this;
    }

    /**
     * @return T[]
     */
    public function get(): array
    {
        return array_map(
            $this->prototype::hydrate(...),
            $this->builder->get(),
        );
    }

    /**
     * @return T|null
     */
    public function first(): ?OdooModel
    {
        $items = $this->limit(1)->get();

        return $items[0] ?? null;
    }

    public function count(): int
    {
        return $this->builder->count();
    }

    /**
     * @return int[]
     */
    public function ids(): array
    {
        return $this->builder->ids();
    }
}

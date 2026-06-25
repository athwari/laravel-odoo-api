<?php

namespace Athwari\LaravelOdooApi\Odoo\Models;

use Athwari\LaravelOdooApi\Exceptions\ConfigurationException;
use Athwari\LaravelOdooApi\Odoo\OdooModel;
use Athwari\LaravelOdooApi\Odoo\Request\RequestBuilder;

/**
 * Wraps a RequestBuilder so its terminal methods return hydrated
 * OdooModel instances instead of raw stdClass objects.
 *
 * @template T of OdooModel
 *
 * @mixin RequestBuilder
 *
 * @method $this cache(\DateTimeInterface|\DateInterval|int $ttl, ?string $key = null)
 */
final class ModelQuery
{
    /** @var array<string> */
    private array $with = [];

    public function __construct(
        private readonly OdooModel $prototype,
        private readonly RequestBuilder $builder,
    ) {}

    public function with(string|array $relations): static
    {
        $relations = is_string($relations) ? func_get_args() : $relations;
        $this->with = array_merge($this->with, $relations);

        return $this;
    }

    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->builder->$method(...$parameters);

        if ($result === $this->builder) {
            return $this;
        }

        return $result;
    }

    /**
     * @return T[]|array
     */
    public function get(): array
    {
        $records = $this->builder->get();

        if ($this->builder->getDtoClass()) {
            return $records;
        }

        $models = array_map(
            $this->prototype::hydrate(...),
            $records,
        );

        if ($this->with !== []) {
            EagerLoader::load($models, $this->with);
        }

        return $models;
    }

    /**
     * Get the results as a paginator.
     *
     * @throws ConfigurationException
     */
    public function paginate(int $perPage = 15, string $pageName = 'page', ?int $page = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        if (! class_exists(\Illuminate\Pagination\LengthAwarePaginator::class)) {
            throw new ConfigurationException('Pagination requires the illuminate/pagination package.');
        }

        $page = $page ?: \Illuminate\Pagination\Paginator::resolveCurrentPage($pageName);
        $total = $this->builder->count();

        $models = $total ? $this->offset(($page - 1) * $perPage)->limit($perPage)->get() : [];

        return new \Illuminate\Pagination\LengthAwarePaginator(collect($models), $total, $perPage, $page, [
            'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Chunk the results of the query.
     *
     * @param  callable(\Illuminate\Support\Collection<int, T>, int): (bool|void)  $callback
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->offset(($page - 1) * $count)->limit($count)->get();

            $countResults = count($results);

            if ($countResults == 0) {
                break;
            }

            if ($callback(collect($results), $page) === false) {
                return false;
            }

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * @return T|mixed|null
     */
    public function first(): mixed
    {
        $items = $this->limit(1)->get();

        return $items[0] ?? null;
    }
}

<?php

namespace Athwari\LaravelOdooApi\Odoo\Request;

use Athwari\LaravelOdooApi\Exceptions\ConfigurationException;
use Athwari\LaravelOdooApi\Exceptions\ValidationException;
use Athwari\LaravelOdooApi\Odoo\Endpoint\ObjectEndpoint;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Domain;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasCache;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasDomain;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasFields;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasGroupBy;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasLimit;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasOffset;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasOptions;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasOrder;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Options;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RequestBuilder
{
    use HasCache;
    use HasDomain;
    use HasFields;
    use HasGroupBy;
    use HasLimit;
    use HasOffset;
    use HasOptions;
    use HasOrder;

    protected ?string $dtoClass = null;

    public function __construct(
        private readonly ObjectEndpoint $endpoint,
        protected string $model,
        Domain $domain,
        ?Options $options = null,
    ) {
        $this->domain = $domain;
        $this->options = $options ?? new Options();
    }

    public function as(string $className): static
    {
        $this->dtoClass = $className;

        return $this;
    }

    public function getDtoClass(): ?string
    {
        return $this->dtoClass;
    }

    public function can(string $permission): bool
    {
        return $this->endpoint->checkAccessRights($this->model, $permission, $this->options);
    }

    public function get(): array
    {
        if ($this->cacheTtl !== null) {
            $result = Cache::remember(
                $this->generateCacheKey('get'),
                $this->cacheTtl,
                fn () => $this->executeGet()
            );
        } else {
            $result = $this->executeGet();
        }

        return $this->mapToDto($result);
    }

    private function mapToDto(array $result): array
    {
        if (! $this->dtoClass) {
            return $result;
        }

        $dtoClass = $this->dtoClass;
        $hasFromArray = method_exists($dtoClass, 'fromArray');

        return array_map(function ($record) use ($dtoClass, $hasFromArray) {
            if ($hasFromArray) {
                return $dtoClass::fromArray((array) $record);
            }

            return new $dtoClass((array) $record);
        }, $result);
    }

    private function executeGet(): array
    {
        if ($this->hasGroupBy()) {
            return $this->endpoint->readGroup(
                $this->model,
                groupBy: $this->groupBy,
                domain: $this->domain,
                fields: $this->fields,
                offset: $this->offset,
                limit: $this->limit,
                order: $this->getOrderString(),
                options: $this->options,
            );
        }

        return $this->endpoint->searchRead(
            $this->model,
            domain: $this->domain,
            fields: $this->fields,
            offset: $this->offset,
            limit: $this->limit,
            order: $this->getOrderString(),
            options: $this->options,
        );
    }

    /**
     * Get the results as a Laravel Collection.
     *
     * @return Collection<int, \stdClass>
     *
     * @throws ConfigurationException If Laravel's collect() helper is unavailable
     */
    public function collect(): iterable
    {
        if (! function_exists('collect')) {
            throw new ConfigurationException('collect() is not defined. Are you missing the Laravel framework?');
        }

        return collect($this->get());
    }

    /**
     * Get the results as a paginator.
     *
     * @throws ConfigurationException
     */
    public function paginate(int $perPage = 15, string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        if (! class_exists(LengthAwarePaginator::class)) {
            throw new ConfigurationException('Pagination requires the illuminate/pagination package.');
        }

        $page = $page ?: Paginator::resolveCurrentPage($pageName);
        $total = $this->count();
        $results = $total ? $this->offset(($page - 1) * $perPage)->limit($perPage)->collect() : collect();

        return new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Chunk the results of the query.
     *
     * @param  callable(Collection<int, \stdClass>, int): (bool|void)  $callback
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->offset(($page - 1) * $count)->limit($count)->collect();

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while ($countResults == $count);

        return true;
    }

    public function first(): ?object
    {
        $this->limit = 1;
        $result = $this->get()[0] ?? null;

        return $result ? (object) $result : null;
    }

    public function ids(): array
    {
        return $this->endpoint->search(
            $this->model,
            domain: $this->domain,
            offset: $this->offset,
            limit: $this->limit,
            order: $this->getOrderString(),
            options: $this->options,
        );
    }

    public function count(): int
    {
        if ($this->cacheTtl !== null) {
            return Cache::remember(
                $this->generateCacheKey('count'),
                $this->cacheTtl,
                fn () => $this->executeCount()
            );
        }

        return $this->executeCount();
    }

    private function executeCount(): int
    {
        return $this->endpoint->count(
            $this->model,
            domain: $this->domain,
            offset: $this->offset,
            limit: $this->limit,
            order: $this->getOrderString(),
            options: $this->options,
        );
    }

    /**
     * Delete all records matching the current domain.
     *
     * Requires at least one where() condition to be set, to prevent
     * accidentally deleting every record in the model. Use the
     * underlying endpoint's unlink() directly (with explicit IDs) if
     * you genuinely need an unscoped delete.
     *
     * @throws ValidationException If no where() condition has been set
     */
    public function delete(): bool
    {
        $this->guardAgainstUnscopedWrite('delete');

        $ids = $this->ids();

        if ($ids === []) {
            return true;
        }

        return $this->endpoint->unlink($this->model, $ids, $this->options);
    }

    public function create(array $values): bool|int
    {
        return $this->endpoint->create($this->model, $values, $this->options);
    }

    /**
     * Create multiple records in a single RPC call.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, int> List of created record IDs
     *
     * @throws ValidationException
     */
    public function createMany(array $records): array
    {
        if ($records === []) {
            throw new ValidationException("Cannot create records in '{$this->model}' with empty data.");
        }

        $result = $this->endpoint->executeKw($this->model, 'create', [$records], $this->options);

        return is_array($result) ? $result : [];
    }

    /**
     * Update all records matching the current domain.
     *
     * Requires at least one where() condition to be set, to prevent
     * accidentally overwriting every record in the model. Use the
     * underlying endpoint's write() directly (with explicit IDs) if
     * you genuinely need an unscoped update.
     *
     * @throws ValidationException If no where() condition has been set
     */
    public function write(array $values): bool
    {
        $this->guardAgainstUnscopedWrite('update');

        $ids = $this->ids();

        if ($ids === []) {
            return true;
        }

        return $this->endpoint->write($this->model, $ids, $values, $this->options);
    }

    /**
     * Update multiple records with specific values for each.
     *
     * Groups records with identical values into single RPC calls to optimize network performance.
     *
     * @param  array<int, array{id: int|string, values: array<string, mixed>}>  $payloads
     *
     * @throws ValidationException
     */
    public function writeMany(array $payloads): bool
    {
        if ($payloads === []) {
            throw new ValidationException("Cannot update records in '{$this->model}' with empty data.");
        }

        $grouped = [];

        foreach ($payloads as $index => $payload) {
            if (! isset($payload['id']) || ! isset($payload['values']) || ! is_array($payload['values'])) {
                throw new ValidationException("writeMany payload at index {$index} must contain 'id' and an array of 'values'.");
            }

            $id = $payload['id'];
            $values = $payload['values'];

            if ($values === []) {
                continue;
            }

            ksort($values);
            $hash = md5(json_encode($values) ?: '');

            if (! isset($grouped[$hash])) {
                $grouped[$hash] = [
                    'ids' => [],
                    'values' => $values,
                ];
            }

            $grouped[$hash]['ids'][] = $id;
        }

        foreach ($grouped as $group) {
            $this->endpoint->write($this->model, $group['ids'], $group['values'], $this->options);
        }

        return true;
    }

    public function update(array $values): bool
    {
        return $this->write($values);
    }

    /**
     * @throws ValidationException
     */
    private function guardAgainstUnscopedWrite(string $operation): void
    {
        if ($this->domain->isEmpty()) {
            throw new ValidationException(
                "Refusing to {$operation} all records in '{$this->model}' without a where() condition. "
                .'Use where() to scope this query, or call unlink()/write() on the endpoint directly '
                .'with explicit IDs if an unscoped operation is genuinely intended.',
            );
        }
    }
}

<?php

namespace Athwari\LaravelOdooApi\Odoo\Request;

use Athwari\LaravelOdooApi\Exceptions\ConfigurationException;
use Athwari\LaravelOdooApi\Exceptions\ValidationException;
use Athwari\LaravelOdooApi\Odoo\Endpoint\ObjectEndpoint;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Domain;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasDomain;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasFields;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasGroupBy;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasLimit;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasOffset;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasOptions;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\HasOrder;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Options;

class RequestBuilder
{
    use HasDomain;
    use HasFields;
    use HasGroupBy;
    use HasLimit;
    use HasOffset;
    use HasOptions;
    use HasOrder;

    public function __construct(
        private readonly ObjectEndpoint $endpoint,
        protected string $model,
        Domain $domain,
        ?Options $options = null,
    ) {
        $this->domain = $domain;
        $this->options = $options ?? new Options();
    }

    public function can(string $permission): bool
    {
        return $this->endpoint->checkAccessRights($this->model, $permission, $this->options);
    }

    public function get(): array
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
     * @throws ConfigurationException If Laravel's collect() helper is unavailable
     */
    public function collect(): iterable
    {
        if (! function_exists('collect')) {
            throw new ConfigurationException('collect() is not defined. Are you missing the Laravel framework?');
        }

        return collect($this->get());
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

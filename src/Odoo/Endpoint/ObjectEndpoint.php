<?php

namespace Athwari\LaravelOdooApi\Odoo\Endpoint;

use Athwari\LaravelOdooApi\Exceptions\ValidationException;
use Athwari\LaravelOdooApi\Odoo\Config;
use Athwari\LaravelOdooApi\Odoo\Context;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Domain;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Options;
use Athwari\LaravelOdooApi\Odoo\Request\CheckAccessRights;
use Athwari\LaravelOdooApi\Odoo\Request\Create;
use Athwari\LaravelOdooApi\Odoo\Request\FieldsGet;
use Athwari\LaravelOdooApi\Odoo\Request\Read;
use Athwari\LaravelOdooApi\Odoo\Request\ReadGroup;
use Athwari\LaravelOdooApi\Odoo\Request\Request;
use Athwari\LaravelOdooApi\Odoo\Request\RequestBuilder;
use Athwari\LaravelOdooApi\Odoo\Request\Search;
use Athwari\LaravelOdooApi\Odoo\Request\SearchRead;
use Athwari\LaravelOdooApi\Odoo\Request\Unlink;
use Athwari\LaravelOdooApi\Odoo\Request\Write;

class ObjectEndpoint extends Endpoint
{
    protected string $service = 'object';

    public function __construct(Config $config, protected Context $context, protected int $uid)
    {
        parent::__construct($config);
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
    }

    public function execute(Request $request, ?Options $options = null): mixed
    {
        $options ??= new Options();

        return $request->execute(
            client: $this->getClient(),
            database: $this->getConfig()->getDatabase(),
            uid: $this->uid,
            credential: $this->getConfig()->getCredential(),
            options: $options->withContext($this->context),
        );
    }

    /**
     * Call an arbitrary Odoo model method via execute_kw.
     *
     * Escape hatch for custom/non-standard Odoo methods that don't have
     * a dedicated Request class (e.g. action_confirm, custom business
     * methods on a model). Prefer the typed methods (search/create/...)
     * where one exists.
     *
     * @throws ValidationException If the model name is empty
     */
    public function executeKw(string $model, string $method, array $args = [], array|Options $kwargs = []): mixed
    {
        if (trim($model) === '') {
            throw new ValidationException('Odoo model name cannot be empty.');
        }

        if ($kwargs instanceof Options) {
            $kwargs = $kwargs->toArray();
        }

        $callArgs = [
            $this->getConfig()->getDatabase(),
            $this->uid,
            $this->getConfig()->getCredential(),
            $model,
            $method,
            $args,
        ];

        if ($kwargs !== []) {
            $callArgs[] = $kwargs;
        }

        return $this->getClient()->execute_kw(...$callArgs);
    }

    public function model(string $model, ?Domain $domain = null): RequestBuilder
    {
        return new RequestBuilder(
            endpoint: $this,
            model: $model,
            domain: $domain ?? new Domain(),
        );
    }

    public function checkAccessRights(string $model, string $permission, ?Options $options = null): bool
    {
        return $this->execute(new CheckAccessRights(
            model: $model,
            permission: $permission,
        ), $options);
    }

    public function count(string $model, ?Domain $domain = null, int $offset = 0, ?int $limit = null, ?string $order = null, ?Options $options = null): int
    {
        return $this->execute(new Search(
            model: $model,
            domain: $domain ?? new Domain(),
            offset: $offset,
            limit: $limit,
            order: $order,
            count: true,
        ), $options);
    }

    public function search(string $model, ?Domain $domain = null, int $offset = 0, ?int $limit = null, ?string $order = null, ?Options $options = null): array
    {
        return $this->execute(new Search(
            model: $model,
            domain: $domain ?? new Domain(),
            offset: $offset,
            limit: $limit,
            order: $order,
        ), $options);
    }

    public function read(string $model, array $ids, array $fields = [], ?Options $options = null): array
    {
        if ($ids === []) {
            return [];
        }

        $results = $this->execute(new Read(
            model: $model,
            ids: $ids,
            fields: $fields,
        ), $options);

        return array_map(static fn ($item) => (object) $item, $results);
    }

    public function searchRead(string $model, ?Domain $domain = null, ?array $fields = null, int $offset = 0, ?int $limit = null, ?string $order = null, ?Options $options = null): array
    {
        $results = $this->execute(new SearchRead(
            model: $model,
            domain: $domain ?? new Domain(),
            fields: $fields,
            offset: $offset,
            limit: $limit,
            order: $order,
        ), $options);

        return array_map(static fn ($item) => (object) $item, $results);
    }

    public function readGroup(string $model, array $groupBy, ?Domain $domain = null, ?array $fields = null, int $offset = 0, ?int $limit = null, ?string $order = null, ?Options $options = null): array
    {
        $results = $this->execute(new ReadGroup(
            model: $model,
            groupBy: $groupBy,
            domain: $domain ?? new Domain(),
            fields: $fields,
            offset: $offset,
            limit: $limit,
            order: $order,
        ), $options);

        return array_map(static fn ($item) => (object) $item, $results);
    }

    public function fieldsGet(string $model, ?array $fields = null, ?array $attributes = null, ?Options $options = null): object
    {
        return (object) $this->execute(new FieldsGet(
            model: $model,
            fields: $fields,
            attributes: $attributes,
        ), $options);
    }

    public function create(string $model, array $values, ?Options $options = null): bool|int
    {
        if ($values === []) {
            throw new ValidationException("Cannot create a record in '{$model}' with empty data.");
        }

        return $this->execute(new Create(
            model: $model,
            values: $values,
        ), $options);
    }

    public function unlink(string $model, array $ids, ?Options $options = null): bool
    {
        if ($ids === []) {
            return true;
        }

        return $this->execute(new Unlink(
            model: $model,
            ids: $ids,
        ), $options);
    }

    public function write(string $model, array $ids, array $values, ?Options $options = null): bool
    {
        if ($values === []) {
            throw new ValidationException("Cannot update records in '{$model}' with empty data.");
        }

        if ($ids === []) {
            return true;
        }

        return $this->execute(new Write(
            model: $model,
            ids: $ids,
            values: $values,
        ), $options);
    }
}

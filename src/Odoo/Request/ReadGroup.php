<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Request;

use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Domain;

/**
 * Groups records by one or more fields and returns aggregated data.
 */
class ReadGroup extends Request
{
    public function __construct(
        string $model,
        protected array $groupBy,
        protected Domain $domain,
        protected ?array $fields = null,
        protected int $offset = 0,
        protected ?int $limit = null,
        protected ?string $order = null,
    ) {
        parent::__construct($model, 'read_group');
    }

    public function toArray(): array
    {
        return [
            $this->domain->toArray(),
            $this->fields,
            $this->groupBy,
            $this->offset,
            $this->limit,
            $this->order,
        ];
    }
}

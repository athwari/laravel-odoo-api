<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Request;

use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Domain;

/**
 * Searches for models and returns their field data in one call.
 */
class SearchRead extends Request
{
    public function __construct(
        string $model,
        protected Domain $domain,
        protected ?array $fields = null,
        protected int $offset = 0,
        protected ?int $limit = null,
        protected ?string $order = null,
    ) {
        parent::__construct($model, 'search_read');
    }

    public function toArray(): array
    {
        return [
            $this->domain->toArray(),
            $this->fields,
            $this->offset,
            $this->limit,
            $this->order,
        ];
    }
}

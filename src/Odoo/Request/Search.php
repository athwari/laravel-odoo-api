<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Request;

use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Domain;

/**
 * Searches for model IDs matching a domain. Also used for count
 * (via the $count flag) since Odoo's search() and search_count()
 * share the same argument shape.
 */
class Search extends Request
{
    public function __construct(
        string $model,
        protected Domain $domain,
        protected int $offset = 0,
        protected ?int $limit = null,
        protected ?string $order = null,
        protected bool $count = false,
    ) {
        parent::__construct($model, $count ? 'search_count' : 'search');
    }

    public function toArray(): array
    {
        if ($this->count) {
            return [
                $this->domain->toArray(),
            ];
        }

        return [
            $this->domain->toArray(),
            $this->offset,
            $this->limit,
            $this->order,
        ];
    }
}

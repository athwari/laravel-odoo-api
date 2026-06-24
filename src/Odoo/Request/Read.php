<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Request;

/**
 * Reads field data for a known set of record IDs.
 */
class Read extends Request
{
    /**
     * @param  int[]  $ids
     * @param  string[]  $fields
     */
    public function __construct(
        string $model,
        private readonly array $ids,
        private readonly array $fields = [],
    ) {
        parent::__construct($model, 'read');
    }

    public function toArray(): array
    {
        return [$this->ids, $this->fields];
    }
}

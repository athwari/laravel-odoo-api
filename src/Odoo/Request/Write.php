<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Request;

class Write extends Request
{
    /**
     * @param  int[]  $ids
     */
    public function __construct(
        string $model,
        private readonly array $ids,
        private readonly array $values,
    ) {
        parent::__construct($model, 'write');
    }

    public function toArray(): array
    {
        return [$this->ids, $this->values];
    }
}

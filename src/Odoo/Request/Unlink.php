<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Request;

class Unlink extends Request
{
    /**
     * @param  int[]  $ids
     */
    public function __construct(
        string $model,
        private readonly array $ids,
    ) {
        parent::__construct($model, 'unlink');
    }

    public function toArray(): array
    {
        return [$this->ids];
    }
}

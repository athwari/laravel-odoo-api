<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Request;

class Create extends Request
{
    public function __construct(
        string $model,
        private readonly array $values,
    ) {
        parent::__construct($model, 'create');
    }

    public function toArray(): array
    {
        return [$this->values];
    }
}

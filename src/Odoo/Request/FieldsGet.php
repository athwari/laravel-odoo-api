<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Request;

/**
 * Returns the field definitions (introspection metadata) for a model.
 */
class FieldsGet extends Request
{
    public function __construct(
        string $model,
        private readonly ?array $fields,
        private readonly ?array $attributes,
    ) {
        parent::__construct($model, 'fields_get');
    }

    public function toArray(): array
    {
        return [$this->fields, $this->attributes];
    }
}

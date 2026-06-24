<?php

namespace Athwari\LaravelOdooApi\Odoo\Request\Arguments;

trait HasFields
{
    protected ?array $fields = null;

    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }
}

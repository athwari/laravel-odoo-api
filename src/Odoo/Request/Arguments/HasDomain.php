<?php

namespace Athwari\LaravelOdooApi\Odoo\Request\Arguments;

trait HasDomain
{
    protected Domain $domain;

    public function where(string $field, string $operator, mixed $value): static
    {
        $this->domain->where($field, $operator, $value);

        return $this;
    }

    public function whereNot(string $field, string $operator, mixed $value): static
    {
        $this->domain->whereNot($field, $operator, $value);

        return $this;
    }

    public function orWhere(string $field, string $operator, mixed $value): static
    {
        $this->domain->orWhere($field, $operator, $value);

        return $this;
    }
}

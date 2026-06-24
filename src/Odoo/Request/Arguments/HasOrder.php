<?php

namespace Athwari\LaravelOdooApi\Odoo\Request\Arguments;

trait HasOrder
{
    protected array $order = [];

    public function orderBy(string $field, string $direction = 'asc'): static
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $this->order[] = "{$field} {$direction}";

        return $this;
    }

    protected function getOrderString(): ?string
    {
        return empty($this->order) ? null : implode(',', $this->order);
    }
}

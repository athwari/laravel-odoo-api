<?php

namespace Athwari\LaravelOdooApi\Odoo\Request\Arguments;

trait HasGroupBy
{
    protected ?array $groupBy = null;

    public function groupBy(array $groupBy): static
    {
        $this->groupBy = $groupBy;

        return $this;
    }

    public function hasGroupBy(): bool
    {
        return $this->groupBy !== null;
    }
}

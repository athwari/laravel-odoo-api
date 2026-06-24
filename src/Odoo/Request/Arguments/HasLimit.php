<?php

namespace Athwari\LaravelOdooApi\Odoo\Request\Arguments;

trait HasLimit
{
    protected ?int $limit = null;

    public function limit(?int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }
}

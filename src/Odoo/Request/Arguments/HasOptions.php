<?php

namespace Athwari\LaravelOdooApi\Odoo\Request\Arguments;

trait HasOptions
{
    protected Options $options;

    public function option(string $key, mixed $value): static
    {
        $this->options->setRaw($key, $value);

        return $this;
    }
}

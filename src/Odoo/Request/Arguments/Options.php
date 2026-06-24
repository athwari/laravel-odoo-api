<?php

namespace Athwari\LaravelOdooApi\Odoo\Request\Arguments;

use Athwari\LaravelOdooApi\Odoo\Context;

/**
 * Keyword-argument bag for an execute_kw call (limit/offset/context/etc).
 */
class Options
{
    public function __construct(
        private array $options = [],
        private ?Context $context = null,
    ) {}

    public function toArray(): array
    {
        $context = $this->context?->toArray() ?? [];

        if ($context === []) {
            return $this->options;
        }

        return ['context' => $context] + $this->options;
    }

    public function withContext(Context $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function setRaw(string $key, mixed $value): static
    {
        $this->options[$key] = $value;

        return $this;
    }

    public function limit(int $value): static
    {
        return $this->setRaw('limit', $value);
    }

    public function offset(int $value): static
    {
        return $this->setRaw('offset', $value);
    }
}

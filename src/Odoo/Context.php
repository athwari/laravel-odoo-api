<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo;

/**
 * Odoo request context: language, timezone, company, and arbitrary
 * extra context keys sent with every RPC call (e.g. lang for
 * translated fields, company_id for multi-company filtering).
 */
final class Context
{
    public function __construct(
        private ?string $lang = null,
        private ?string $timezone = null,
        private ?int $companyId = null,
        private array $contextArgs = [],
    ) {}

    public function setContextArg(string $key, mixed $value): void
    {
        $this->contextArgs[$key] = $value;
    }

    public function toArray(): array
    {
        return array_filter([
            'lang' => $this->lang,
            'tz' => $this->timezone,
            'company_id' => $this->companyId,
            ...$this->contextArgs,
        ], static fn ($value) => $value !== null);
    }

    public function clone(): static
    {
        return clone $this;
    }

    /**
     * Fill any unset fields on this context from a fallback context,
     * without overwriting values already set here. Used to apply a
     * default/global Context underneath a per-call Options context.
     */
    public function setDefaults(?Context $context): static
    {
        if ($context instanceof Context) {
            $this->lang ??= $context->lang;
            $this->timezone ??= $context->timezone;
            $this->companyId ??= $context->companyId;

            foreach ($context->contextArgs as $key => $value) {
                if (! array_key_exists($key, $this->contextArgs)) {
                    $this->setContextArg($key, $value);
                }
            }
        }

        return $this;
    }
}

<?php

namespace Athwari\LaravelOdooApi\Odoo\Casts;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Casts Odoo date/datetime strings to PHP DateTimeImmutable objects.
 *
 * Prefer this over DateTimeCast when you want immutable date values
 * (no accidental mutation through setTimezone/modify/etc).
 *
 *   Odoo::registerCast(new DateTimeImmutableCast());
 *   Odoo::registerCast(new DateTimeImmutableCast('Europe/Berlin'));
 *   Odoo::registerCast(new DateTimeImmutableCast(new DateTimeZone('UTC')));
 *
 * @extends Cast<DateTimeImmutable>
 */
class DateTimeImmutableCast extends Cast
{
    private readonly ?DateTimeZone $timezone;

    public function __construct(DateTimeZone|string|null $timezone = null)
    {
        $this->timezone = match (true) {
            $timezone instanceof DateTimeZone => $timezone,
            is_string($timezone) => new DateTimeZone($timezone),
            default => null,
        };
    }

    public function getType(): string
    {
        return DateTimeImmutable::class;
    }

    public function cast(mixed $raw): ?DateTimeImmutable
    {
        if (! $raw) {
            return null;
        }

        try {
            $dt = new DateTimeImmutable($raw);

            if ($this->timezone instanceof DateTimeZone) {
                $dt = $dt->setTimezone($this->timezone);
            }

            return $dt;
        } catch (Exception) {
            return null;
        }
    }

    public function uncast(mixed $value): mixed
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value ?? false;
    }
}

<?php

namespace Athwari\LaravelOdooApi\Odoo\Casts;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * Casts Odoo date/datetime strings (UTC, format 'Y-m-d H:i:s') to PHP
 * DateTime objects, and back again on write.
 *
 * An optional target timezone can be supplied so the resulting DateTime
 * is expressed in that zone rather than the raw UTC string from Odoo:
 *
 *   Odoo::registerCast(new DateTimeCast());                        // UTC
 *   Odoo::registerCast(new DateTimeCast('Europe/Berlin'));          // named tz
 *   Odoo::registerCast(new DateTimeCast(new DateTimeZone('Asia/Dubai'))); // object
 *
 * On uncast (write back to Odoo), the DateTime is always formatted as
 * 'Y-m-d H:i:s' in its own timezone — Odoo's backend stores everything
 * in UTC, so ensure you're writing UTC values or letting Odoo convert.
 *
 * @extends Cast<DateTime>
 */
class DateTimeCast extends Cast
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
        return DateTime::class;
    }

    public function cast(mixed $raw): ?DateTime
    {
        if (! $raw) {
            return null;
        }

        try {
            $dt = new DateTime($raw);

            if ($this->timezone instanceof DateTimeZone) {
                $dt->setTimezone($this->timezone);
            }

            return $dt;
        } catch (Exception) {
            // Invalid date string from Odoo; treat as unset.
            return null;
        }
    }

    public function uncast(mixed $value): mixed
    {
        if ($value instanceof DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        // Odoo expects `false` for an empty date/datetime field, not null.
        return $value ?? false;
    }
}

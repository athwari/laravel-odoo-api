<?php

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\Odoo\Casts\DateTimeCast;
use Athwari\LaravelOdooApi\Odoo\Casts\DateTimeImmutableCast;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;

test('cast converts a date string to datetime', function () {
    $cast = new DateTimeCast();

    $result = $cast->cast('2026-01-15 10:30:00');

    expect($result)->toBeInstanceOf(DateTime::class);
    expect($result->format('Y-m-d H:i:s'))->toBe('2026-01-15 10:30:00');
});

test('cast returns null for empty value', function () {
    $cast = new DateTimeCast();

    expect($cast->cast(false))->toBeNull();
    expect($cast->cast(null))->toBeNull();
    expect($cast->cast(''))->toBeNull();
});

test('cast returns null for an invalid date string', function () {
    $cast = new DateTimeCast();

    expect($cast->cast('not a real date'))->toBeNull();
});

test('uncast converts datetime to odoo format', function () {
    $cast = new DateTimeCast();

    $result = $cast->uncast(new DateTime('2026-01-15 10:30:00'));

    expect($result)->toBe('2026-01-15 10:30:00');
});

test('uncast converts null to false', function () {
    $cast = new DateTimeCast();

    expect($cast->uncast(null))->toBeFalse();
});

/**
 * Regression test: the original implementation this package is
 * derived from had no `return` in the non-DateTime branch of
 * uncast(), silently discarding any non-null, non-DateTime value
 * (e.g. an already-formatted string) instead of passing it
 * through unchanged.
 */
test('uncast passes through non datetime non null values unchanged', function () {
    $cast = new DateTimeCast();

    expect($cast->uncast('2026-01-15 10:30:00'))->toBe('2026-01-15 10:30:00');
});

// -- Timezone support -------------------------------------------------

test('cast applies timezone when constructed with a string', function () {
    $cast = new DateTimeCast('Europe/Berlin');

    // Odoo always returns UTC; the cast should shift to the target tz.
    $result = $cast->cast('2026-01-15 10:00:00');

    expect($result)->toBeInstanceOf(DateTime::class);
    expect($result->getTimezone()->getName())->toBe('Europe/Berlin');
});

test('cast applies timezone when constructed with a datetimezone', function () {
    $tz = new DateTimeZone('Asia/Dubai');
    $cast = new DateTimeCast($tz);

    $result = $cast->cast('2026-06-01 12:00:00');

    expect($result)->toBeInstanceOf(DateTime::class);
    expect($result->getTimezone()->getName())->toBe('Asia/Dubai');
});

test('cast with no timezone uses the raw string timezone', function () {
    $cast = new DateTimeCast();

    $result = $cast->cast('2026-01-15 10:00:00');

    // Without a timezone, DateTime parses using the default PHP timezone.
    expect($result)->toBeInstanceOf(DateTime::class);
});

// -- DateTimeImmutableCast -------------------------------------------

test('immutable cast returns datetimeimmutable', function () {
    $cast = new DateTimeImmutableCast();

    $result = $cast->cast('2026-03-10 08:00:00');

    expect($result)->toBeInstanceOf(DateTimeImmutable::class);
    expect($result->format('Y-m-d H:i:s'))->toBe('2026-03-10 08:00:00');
});

test('immutable cast returns null for empty', function () {
    $cast = new DateTimeImmutableCast();

    expect($cast->cast(false))->toBeNull();
    expect($cast->cast(null))->toBeNull();
});

test('immutable cast applies timezone', function () {
    $cast = new DateTimeImmutableCast('Europe/London');

    $result = $cast->cast('2026-07-01 14:00:00');

    expect($result)->toBeInstanceOf(DateTimeImmutable::class);
    expect($result->getTimezone()->getName())->toBe('Europe/London');
});

test('immutable uncast returns false for null', function () {
    $cast = new DateTimeImmutableCast();

    expect($cast->uncast(null))->toBeFalse();
});

test('immutable uncast formats correctly', function () {
    $cast = new DateTimeImmutableCast();
    $dt = new DateTimeImmutable('2026-11-25 09:15:00');

    expect($cast->uncast($dt))->toBe('2026-11-25 09:15:00');
});

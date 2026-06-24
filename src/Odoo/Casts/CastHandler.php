<?php

namespace Athwari\LaravelOdooApi\Odoo\Casts;

use ReflectionProperty;

class CastHandler
{
    /** @var array<string, Cast[]> */
    private static array $classHandlers = [];

    /** @var Cast[] */
    private static array $wildcardHandlers = [];

    public static function hasCasts(): bool
    {
        return self::$classHandlers !== [] || self::$wildcardHandlers !== [];
    }

    public static function registerCast(Cast $cast): void
    {
        $type = $cast->getType();

        if ($type === Cast::WILDCARD) {
            self::$wildcardHandlers[] = $cast;

            return;
        }

        self::$classHandlers[$type] ??= [];
        self::$classHandlers[$type][] = $cast;

        if ($cast->handlesNullValues()) {
            self::$classHandlers["?{$type}"] ??= [];
            self::$classHandlers["?{$type}"][] = $cast;
        }
    }

    /**
     * Clear all registered casts. Primarily useful for test isolation,
     * since cast registration is process-global.
     */
    public static function reset(): void
    {
        self::$classHandlers = [];
        self::$wildcardHandlers = [];
    }

    public static function cast(ReflectionProperty $property, mixed $raw): mixed
    {
        return self::dispatch($property, $raw, static fn (Cast $cast, $value) => $cast->cast($value));
    }

    public static function uncast(ReflectionProperty $property, mixed $value): mixed
    {
        return self::dispatch($property, $value, static fn (Cast $cast, $value) => $cast->uncast($value));
    }

    private static function dispatch(ReflectionProperty $property, mixed $value, callable $apply): mixed
    {
        $type = (string) $property->getType();

        $handlers = self::$classHandlers[$type] ?? self::$wildcardHandlers;

        foreach ($handlers as $handler) {
            if ($handler->applies($value)) {
                return $apply($handler, $value);
            }
        }

        return $value;
    }
}

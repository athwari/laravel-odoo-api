<?php

namespace Athwari\LaravelOdooApi\Odoo\Mapping;

use Athwari\LaravelOdooApi\Attributes\BelongsTo;
use Athwari\LaravelOdooApi\Attributes\Field;
use Athwari\LaravelOdooApi\Attributes\HasMany;
use Athwari\LaravelOdooApi\Attributes\Key;
use Athwari\LaravelOdooApi\Attributes\KeyName;
use Athwari\LaravelOdooApi\Odoo\Casts\CastHandler;
use Athwari\LaravelOdooApi\Odoo\Models\LazyHasMany;
use Athwari\LaravelOdooApi\Odoo\OdooModel;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

/**
 * @property int|null $id
 */
trait HasFields
{
    /**
     * Guards against unbounded recursion when eagerly resolving
     * #[BelongsTo] relations on self-referential or cyclic models
     * (e.g. Partner belongsTo Partner via parent_id). Each nested
     * find() call made to resolve a relation increments this; once
     * the limit is hit, further BelongsTo properties are left null
     * rather than triggering another RPC round-trip.
     *
     * This is a blunt safety net, not a substitute for designing
     * around deep/cyclic relation graphs. If you need to walk an
     * arbitrarily deep hierarchy (e.g. all ancestors of a partner),
     * do it explicitly via repeated find() calls rather than relying
     * on #[BelongsTo] to do it implicitly.
     */
    private static int $belongsToDepth = 0;

    private static int $maxBelongsToDepth = 3;

    protected static function fieldNames(): array
    {
        $fieldNames = [];

        foreach ((new ReflectionClass(static::class))->getProperties() as $property) {
            foreach ($property->getAttributes(Field::class) as $attribute) {
                $fieldNames[] = $attribute->newInstance()->name ?? $property->name;
            }
        }

        return $fieldNames;
    }

    public static function hydrate(array|object $response): static
    {
        $id = null;
        if (is_array($response)) {
            $id = $response['id'] ?? null;
            $response = (object) $response;
        } elseif (is_object($response)) {
            $id = $response->id ?? null;
        }

        $castsExist = CastHandler::hasCasts();

        $reflectionClass = new ReflectionClass(static::class);
        $instance = static::newInstance();

        if ($id !== null) {
            $instance->id = $id;
        }

        foreach ($reflectionClass->getProperties() as $property) {
            static::hydrateField($property, $response, $instance, $castsExist);
            static::hydrateBelongsTo($property, $response, $instance);
            static::hydrateHasMany($property, $response, $instance);
        }

        return $instance;
    }

    protected static function hydrateField(
        ReflectionProperty $property,
        object $response,
        object $instance,
        bool $castsExist,
    ): void {
        if ($property->getAttributes(BelongsTo::class) !== [] || $property->getAttributes(HasMany::class) !== []) {
            return;
        }

        $isKey = $property->getAttributes(Key::class) !== [];
        $isKeyName = $property->getAttributes(KeyName::class) !== [];

        foreach ($property->getAttributes(Field::class) as $attribute) {
            $field = $attribute->newInstance()->name ?? $property->name;

            if (! isset($response->{$field})) {
                continue;
            }

            $raw = $response->{$field};

            $value = match (true) {
                $isKey => $raw[0] ?? null,
                $isKeyName => $raw[1] ?? null,
                default => $raw,
            };

            $instance->{$property->name} = $castsExist
                ? CastHandler::cast($property, $value)
                : $value;
        }
    }

    /**
     * Resolve a #[BelongsTo] relation at hydration time: the
     * field value is the [id, display_name] tuple Odoo returns for a
     * many2one field. We no longer eagerly fetch the related model
     * here (to avoid N+1 queries). Instead, the property is left
     * uninitialized and will be lazy-loaded via __get() when accessed,
     * or bulk-loaded via eager loading (with()).
     */
    protected static function hydrateBelongsTo(ReflectionProperty $property, object $response, object $instance): void
    {
        $attributes = $property->getAttributes(BelongsTo::class);

        if ($attributes === []) {
            return;
        }

        /** @var BelongsTo $belongsTo */
        $belongsTo = $attributes[0]->newInstance();
        $field = $belongsTo->name;

        if (! isset($response->{$field})) {
            return;
        }

        $raw = $response->{$field};

        if (! is_array($raw) || ! isset($raw[0])) {
            // Odoo returns `false` for an unset many2one field.
            $instance->{$property->name} = null;

            return;
        }

        // We do NOT eagerly call find() here anymore.
        // It will be lazy-loaded by OdooModel::__get() or bulk loaded by EagerLoader.
        if (method_exists($instance, 'setRelationId')) {
            $instance->setRelationId($property->name, (int) $raw[0]);
        }
    }

    /**
     * Wire a #[HasMany] property up as a LazyHasMany collection backed
     * by the raw ID list Odoo returns for a one2many/many2many field.
     * No data is fetched from Odoo until the collection is accessed.
     */
    protected static function hydrateHasMany(ReflectionProperty $property, object $response, object $instance): void
    {
        $attributes = $property->getAttributes(HasMany::class);

        if ($attributes === []) {
            return;
        }

        /** @var HasMany $hasMany */
        $hasMany = $attributes[0]->newInstance();
        $field = $hasMany->name;

        $ids = isset($response->{$field}) && is_array($response->{$field})
            ? $response->{$field}
            : [];

        $instance->{$property->name} = new LazyHasMany($hasMany->class, $ids);
    }

    public static function dehydrate(OdooModel $model): object
    {
        $castsExist = CastHandler::hasCasts();
        $item = new stdClass();

        foreach ((new ReflectionClass(static::class))->getProperties() as $property) {
            static::dehydrateField($property, $model, $item, $castsExist);
            static::dehydrateHasMany($property, $model, $item);
        }

        return $item;
    }

    protected static function dehydrateField(ReflectionProperty $property, OdooModel $model, stdClass $item, bool $castsExist): void
    {
        if ($property->getAttributes(BelongsTo::class) !== [] || $property->getAttributes(HasMany::class) !== []) {
            return;
        }

        foreach ($property->getAttributes(Field::class) as $attribute) {
            if (! $property->isInitialized($model)) {
                continue;
            }

            $field = $attribute->newInstance()->name ?? $property->name;
            $value = $model->{$property->name};

            // A field also tagged #[HasMany] is write-handled separately
            // in dehydrateHasMany(); skip it here to avoid double-writing
            // or serializing a LazyHasMany object as a plain field value.
            if ($value instanceof LazyHasMany) {
                continue;
            }

            // Handle foreign key fields that might be arrays [id, name]
            $keyAttrs = $property->getAttributes(Key::class);
            $belongsToAttrs = $property->getAttributes(BelongsTo::class);
            $isKey = count($keyAttrs) > 0 || count($belongsToAttrs) > 0;
            if ($isKey && is_array($value) && count($value) >= 1) {
                $value = $value[0]; // Extract just the ID part
            }

            $item->{$field} = $castsExist ? CastHandler::uncast($property, $value) : $value;
        }
    }

    /**
     * Translate a #[HasMany] property's current value into Odoo's
     * write-command format for one2many/many2many fields:
     *   [6, 0, $ids]              — replace the set with these existing IDs
     *   [0, 0, $values]           — create a new related record
     *   [1, $id, $values]         — update an existing related record
     */
    protected static function dehydrateHasMany(ReflectionProperty $property, OdooModel $model, stdClass $item): void
    {
        $attributes = $property->getAttributes(HasMany::class);

        if ($attributes === [] || ! $property->isInitialized($model)) {
            return;
        }

        /** @var HasMany $hasMany */
        $hasMany = $attributes[0]->newInstance();
        $field = $hasMany->name;
        $values = $model->{$property->name};

        if ($values === null) {
            return;
        }

        if ($values instanceof LazyHasMany) {
            if (! $values->isLoaded()) {
                // Untouched relation: nothing to write back.
                return;
            }
            $values = $values->toArray();
        }

        if (self::isIdArray($values)) {
            $item->{$field} = [[6, 0, array_values($values)]];

            return;
        }

        $commands = [];

        foreach ($values as $value) {
            if (! $value instanceof OdooModel) {
                continue;
            }

            $commands[] = $value->exists()
                ? [1, $value->id, (array) self::dehydrateRelatedModel($value)]
                : [0, 0, (array) self::dehydrateRelatedModel($value)];
        }

        $item->{$field} = $commands;
    }

    /**
     * Safely dehydrate a related model, ensuring foreign key fields are properly handled.
     */
    protected static function dehydrateRelatedModel(OdooModel $relatedModel): object
    {
        $dehydratedData = $relatedModel->dehydrate($relatedModel);

        // Get reflection info to properly identify foreign key fields
        $reflectionClass = new ReflectionClass($relatedModel);
        $properties = $reflectionClass->getProperties();

        foreach ($properties as $property) {
            $isKey = ! empty($property->getAttributes(Key::class)) || ! empty($property->getAttributes(BelongsTo::class));
            $fieldName = null;

            // Get the field name from Field attribute
            $fieldAttributes = $property->getAttributes(Field::class);
            foreach ($fieldAttributes as $attribute) {
                $fieldName = $attribute->newInstance()->name ?? $property->name;
                break;
            }

            // If this is a foreign key field and exists in dehydrated data
            if ($isKey && $fieldName && property_exists($dehydratedData, $fieldName)) {
                $value = $dehydratedData->{$fieldName};
                if (is_array($value) && count($value) >= 1) {
                    // This is a foreign key field [id, name], extract just the ID
                    $dehydratedData->{$fieldName} = $value[0];
                }
            }
        }

        return $dehydratedData;
    }

    protected static function newInstance(): static
    {
        $ref = new ReflectionClass(static::class);

        return $ref->newInstance();
    }

    private static function isIdArray(iterable $values): bool
    {
        foreach ($values as $item) {
            if (! is_int($item)) {
                return false;
            }
        }

        return true;
    }
}

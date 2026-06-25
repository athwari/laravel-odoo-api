<?php

namespace Athwari\LaravelOdooApi\Odoo\Models;

use Athwari\LaravelOdooApi\Attributes\BelongsTo;
use Athwari\LaravelOdooApi\Attributes\HasMany;
use Athwari\LaravelOdooApi\Odoo\OdooModel;
use ReflectionClass;
use ReflectionProperty;

class EagerLoader
{
    /**
     * @param OdooModel[] $models
     * @param array<string> $relations
     */
    public static function load(array $models, array $relations): void
    {
        if ($models === [] || $relations === []) {
            return;
        }

        $relationsMap = self::parseRelations($relations);

        foreach ($relationsMap as $relation => $nestedRelations) {
            self::loadRelation($models, $relation, $nestedRelations);
        }
    }

    /**
     * @param array<string> $relations
     * @return array<string, array<string>>
     */
    private static function parseRelations(array $relations): array
    {
        $map = [];
        foreach ($relations as $relation) {
            $parts = explode('.', $relation, 2);
            $parent = $parts[0];
            $child = $parts[1] ?? null;

            if (! isset($map[$parent])) {
                $map[$parent] = [];
            }
            if ($child !== null) {
                $map[$parent][] = $child;
            }
        }

        return $map;
    }

    /**
     * @param OdooModel[] $models
     */
    private static function loadRelation(array $models, string $relation, array $nestedRelations): void
    {
        $prototype = $models[0];
        $reflection = new ReflectionClass($prototype);

        if (! $reflection->hasProperty($relation)) {
            return;
        }

        $property = $reflection->getProperty($relation);

        if (! empty($property->getAttributes(BelongsTo::class))) {
            self::loadBelongsTo($models, $property, $relation, $nestedRelations);
        } elseif (! empty($property->getAttributes(HasMany::class))) {
            self::loadHasMany($models, $property, $relation, $nestedRelations);
        }
    }

    /**
     * @param OdooModel[] $models
     */
    private static function loadBelongsTo(array $models, ReflectionProperty $property, string $relation, array $nestedRelations): void
    {
        $belongsTo = $property->getAttributes(BelongsTo::class)[0]->newInstance();
        $relatedClass = $belongsTo->class;

        $ids = [];
        foreach ($models as $model) {
            if ($model->getRelationId($relation) !== null) {
                $ids[] = $model->getRelationId($relation);
            }
        }

        $ids = array_unique($ids);
        if ($ids === []) {
            return;
        }

        /** @var OdooModel[] $relatedModels */
        $relatedModels = $relatedClass::read($ids);
        $dictionary = [];
        foreach ($relatedModels as $rm) {
            $dictionary[$rm->id] = $rm;
        }

        foreach ($models as $model) {
            $fk = $model->getRelationId($relation);
            if ($fk !== null && isset($dictionary[$fk])) {
                $model->{$relation} = $dictionary[$fk];
            } else {
                $model->{$relation} = null;
            }
        }

        if ($nestedRelations !== []) {
            self::load(array_values($dictionary), $nestedRelations);
        }
    }

    /**
     * @param OdooModel[] $models
     */
    private static function loadHasMany(array $models, ReflectionProperty $property, string $relation, array $nestedRelations): void
    {
        $hasMany = $property->getAttributes(HasMany::class)[0]->newInstance();
        $relatedClass = $hasMany->class;

        $ids = [];
        foreach ($models as $model) {
            $lazyCollection = $model->{$relation} ?? null;
            if ($lazyCollection instanceof LazyHasMany) {
                // We need to get the IDs without triggering ensureLoaded()
                // But LazyHasMany's $ids are private.
                // We can add getIds() to LazyHasMany or just access them via closure.
                $ids = array_merge($ids, $lazyCollection->getIds());
            }
        }

        $ids = array_unique($ids);
        if ($ids === []) {
            foreach ($models as $model) {
                $lazyCollection = $model->{$relation} ?? null;
                if ($lazyCollection instanceof LazyHasMany) {
                    $lazyCollection->setLoadedItems([]);
                }
            }
            return;
        }

        /** @var OdooModel[] $relatedModels */
        $relatedModels = $relatedClass::read($ids);
        $dictionary = [];
        foreach ($relatedModels as $rm) {
            $dictionary[$rm->id] = $rm;
        }

        foreach ($models as $model) {
            $lazyCollection = $model->{$relation} ?? null;
            if ($lazyCollection instanceof LazyHasMany) {
                $collectionIds = $lazyCollection->getIds();
                $items = [];
                foreach ($collectionIds as $id) {
                    if (isset($dictionary[$id])) {
                        $items[] = $dictionary[$id];
                    }
                }
                $lazyCollection->setLoadedItems($items);
            }
        }

        if ($nestedRelations !== []) {
            self::load(array_values($dictionary), $nestedRelations);
        }
    }
}

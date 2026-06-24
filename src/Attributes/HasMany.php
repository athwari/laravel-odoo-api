<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Attributes;

use Attribute;

/**
 * Declares a one-to-many / many-to-many relationship: the property
 * holds a lazily-loaded collection of $class instances, backed by the
 * one2many/many2many field named $name on the current model.
 *
 * The property should be type-hinted as iterable; at runtime it holds
 * a LazyHasMany instance.
 *
 * Example:
 *   #[Field('order_line')]
 *   #[HasMany(SaleOrderLine::class, 'order_line')]
 *   public iterable $lines;
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany
{
    /**
     * @param  string  $class  Fully-qualified class name of the related OdooModel
     * @param  string  $name  The Odoo one2many/many2many field name on this model (must match the co-declared #[Field] name)
     */
    public function __construct(
        public readonly string $class,
        public readonly string $name,
    ) {}
}

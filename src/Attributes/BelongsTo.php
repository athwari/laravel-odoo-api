<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Attributes;

use Attribute;

/**
 * Declares an inverse one-to-one / many-to-one relationship: the
 * property holds an instance of $class, resolved from the many2one
 * field named $name on the current model.
 *
 * The property should be type-hinted nullable (e.g. ?Country) since
 * Odoo many2one fields are generally optional.
 *
 * Example:
 *   #[Field('country_id')]
 *   #[BelongsTo('country_id', Country::class)]
 *   public ?Country $country;
 *
 * Accessing $partner->country returns a hydrated Country instance,
 * resolved lazily on first access, or null if the relation is unset.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsTo
{
    /**
     * @param  string  $name  The Odoo many2one field name on this model (must match the co-declared #[Field] name)
     * @param  string  $class  Fully-qualified class name of the related OdooModel
     */
    public function __construct(
        public readonly string $name,
        public readonly string $class,
    ) {}
}

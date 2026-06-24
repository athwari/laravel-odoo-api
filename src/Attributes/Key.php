<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Attributes;

use Attribute;

/**
 * Marks a property as holding the ID half of an Odoo many2one field,
 * which Odoo returns as a two-element [id, display_name] tuple.
 *
 * @see KeyName for capturing the display_name half of the same tuple.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Key implements OdooAttribute {}

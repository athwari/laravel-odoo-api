<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Attributes;

use Attribute;

/**
 * Marks a property as holding the display_name half of an Odoo
 * many2one field's [id, display_name] tuple.
 *
 * @see Key for capturing the id half of the same tuple.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class KeyName implements OdooAttribute {}

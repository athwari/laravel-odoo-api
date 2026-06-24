<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Attributes;

/**
 * Marker interface for attributes that describe a single scalar/value
 * field on an OdooModel (Field, Key, KeyName). Relation attributes
 * (BelongsTo, HasMany) intentionally do not implement this.
 */
interface OdooAttribute {}

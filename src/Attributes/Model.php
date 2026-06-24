<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Model
{
    public function __construct(
        public readonly string $name,
    ) {}
}

<?php

namespace Athwari\LaravelOdooApi\Tests\Models;

use Athwari\LaravelOdooApi\Attributes\BelongsTo;
use Athwari\LaravelOdooApi\Attributes\Field;
use Athwari\LaravelOdooApi\Attributes\HasMany;
use Athwari\LaravelOdooApi\Attributes\Key;
use Athwari\LaravelOdooApi\Attributes\Model;
use Athwari\LaravelOdooApi\Odoo\Models\LazyHasMany;
use Athwari\LaravelOdooApi\Odoo\OdooModel;

#[Model('res.partner')]
class Partner extends OdooModel
{
    #[Field]
    public string $name;

    #[Field('email')]
    public ?string $email = null;

    #[Field('parent_id'), Key]
    public ?int $parentId = null;

    #[Field('parent_id')]
    #[BelongsTo('parent_id', Partner::class)]
    public ?Partner $parent = null;

    /** @var LazyHasMany<Partner> */
    #[Field('child_ids')]
    #[HasMany(Partner::class, 'child_ids')]
    public LazyHasMany|array $children;
}

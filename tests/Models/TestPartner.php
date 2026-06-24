<?php

namespace Athwari\LaravelOdooApi\Tests\Models;

use Athwari\LaravelOdooApi\Attributes\BelongsTo;
use Athwari\LaravelOdooApi\Attributes\Field;
use Athwari\LaravelOdooApi\Attributes\HasMany;
use Athwari\LaravelOdooApi\Attributes\Key;
use Athwari\LaravelOdooApi\Attributes\Model;
use Athwari\LaravelOdooApi\Odoo\Models\LazyHasMany;
use Athwari\LaravelOdooApi\Odoo\OdooModel;
use DateTime;

#[Model('res.partner')]
class TestPartner extends OdooModel
{
    #[Field]
    public string $name;

    #[Field('email')]
    public ?string $email = null;

    #[Field('active')]
    public bool $active;

    #[Field('create_date')]
    public ?DateTime $createDate = null;

    #[Field('parent_id'), Key]
    public ?int $parentId = null;

    #[Field('parent_id')]
    #[BelongsTo('parent_id', TestPartner::class)]
    public ?TestPartner $parent = null;

    /** @var LazyHasMany<TestPartner> */
    #[Field('child_ids')]
    #[HasMany(TestPartner::class, 'child_ids')]
    public LazyHasMany $children;
}

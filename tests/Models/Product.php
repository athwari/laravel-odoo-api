<?php

namespace Athwari\LaravelOdooApi\Tests\Models;

use Athwari\LaravelOdooApi\Attributes\Field;
use Athwari\LaravelOdooApi\Attributes\Key;
use Athwari\LaravelOdooApi\Attributes\Model;
use Athwari\LaravelOdooApi\Odoo\OdooModel;

#[Model('product.product')]
class Product extends OdooModel
{
    #[Field('default_code')]
    public ?string $defaultCode = null;

    #[Field('barcode')]
    public ?string $barcode = null;

    #[Field('name')]
    public string $name;

    #[Field('display_name')]
    public string $displayName;

    #[Field('description')]
    public ?string $description = null;

    #[Field('categ_id'), Key]
    public int $categoryId;

    //    #[Field('product_brand_id'), Key]
    //    public ?int $brandId;

    #[Field('uom_id'), Key]
    public int $uomId;

    #[Field('volume')]
    public ?float $volume = null;

    #[Field('weight')]
    public ?float $weight = null;

    #[Field('active')]
    public bool $active;
}

<?php

namespace Athwari\LaravelOdooApi\Tests\Models;

use Athwari\LaravelOdooApi\Attributes\BelongsTo;
use Athwari\LaravelOdooApi\Attributes\Field;
use Athwari\LaravelOdooApi\Attributes\Key;
use Athwari\LaravelOdooApi\Attributes\Model;
use Athwari\LaravelOdooApi\Odoo\OdooModel;

#[Model('sale.order.line')]
class SaleOrderLine extends OdooModel
{
    #[Field('name')]
    public string $name;

    #[Field('product_id'), Key]
    public int $productId;

    #[Field('product_id')]
    #[BelongsTo('product_id', Product::class)]
    public ?Product $product = null;

    #[Field('product_template_id'), Key]
    public ?int $productTemplateId = null;

    #[Field('barcode')]
    public ?string $barcode = null;

    #[Field('product_uom_qty')]
    public float $productQuantity;

    #[Field('product_uom'), Key]
    public ?int $productUomId = null;

    #[Field('price_unit')]
    public float $priceUnit;

    #[Field('discount')]
    public ?float $discount = null;

    #[Field('qty_delivered')]
    public ?float $quantityDelivered = null;

    #[Field('qty_to_deliver')]
    public ?float $quantityToDeliver = null;

    #[Field('qty_invoiced')]
    public ?float $quantityInvoiced = null;

    #[Field('qty_to_invoice')]
    public ?float $quantityToInvoice = null;
}

<?php

namespace Athwari\LaravelOdooApi\Tests\Models;

use Athwari\LaravelOdooApi\Attributes\BelongsTo;
use Athwari\LaravelOdooApi\Attributes\Field;
use Athwari\LaravelOdooApi\Attributes\Key;
use Athwari\LaravelOdooApi\Attributes\Model;
use Athwari\LaravelOdooApi\Odoo\OdooModel;

#[Model('purchase.order.line')]
class PurchaseOrderLine extends OdooModel
{
    #[Field]
    public string $name;

    #[Field('product_id'), Key]
    public int $productId;

    #[Field('product_id')]
    #[BelongsTo('product_id', Product::class)]
    public ?Product $product = null;

    #[Field('order_id'), Key]
    public int $orderId;

    #[Field('order_id')]
    #[BelongsTo('order_id', PurchaseOrder::class)]
    public ?PurchaseOrder $order = null;

    #[Field('product_qty')]
    public float $productQuantity;

    #[Field('price_unit')]
    public float $priceUnit;
}

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

#[Model('sale.order')]
class SaleOrder extends OdooModel
{
    /** @var LazyHasMany<SaleOrderLine> */
    #[Field('order_line')]
    #[HasMany(SaleOrderLine::class, 'order_line')]
    public LazyHasMany|array $lines;

    #[Field('partner_id'), Key]
    public int $partnerId;

    #[Field('partner_id')]
    #[BelongsTo('partner_id', Partner::class)]
    public ?Partner $partner = null;

    #[Field('user_id'), Key]
    public ?int $userId = null;

    #[Field('name')]
    public string $name;

    #[Field('pricelist_id'), Key]
    public ?int $pricelistId = null;

    #[Field('warehouse_id'), Key]
    public ?int $warehouseId = null;

    #[Field('date_order')]
    public DateTime $orderDate;

    #[Field('effective_date')]
    public ?DateTime $effectiveDate = null;

    #[Field('payment_term_id'), Key]
    public ?int $paymentTermId = null;

    #[Field('note')]
    public ?string $note = null;

    #[Field('client_order_ref')]
    public ?string $clientOrderRef = null;

    #[Field('delivery_status')]
    public ?string $deliveryStatus = null;

    #[Field('invoice_status')]
    public ?string $invoiceStatus = null;

    #[Field('invoice_count')]
    public ?int $invoiceCount = null;

    #[Field('state')]
    public ?string $state = null;

    /*
    |--------------------------------------------------------------------------
    | Example custom field
    |--------------------------------------------------------------------------
    |
    | Odoo Studio / custom-module fields (typically prefixed x_) map onto
    | OdooModel properties exactly like standard fields. Remove this
    | example and add your own organization's custom fields here, or
    | extend this class in your own application.
    |
    */

    #[Field('x_custom_reference')]
    public ?string $customReference = null;
}

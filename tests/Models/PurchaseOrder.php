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

#[Model('purchase.order')]
class PurchaseOrder extends OdooModel
{
    /** @var LazyHasMany<PurchaseOrderLine> */
    #[Field('order_line')]
    #[HasMany(PurchaseOrderLine::class, 'order_line')]
    public LazyHasMany|array $lines;

    #[Field('partner_id'), Key]
    public int $partnerId;

    #[Field('partner_id')]
    #[BelongsTo('partner_id', Partner::class)]
    public ?Partner $partner = null;

    #[Field('date_order')]
    public DateTime $orderDate;

    #[Field('date_approve')]
    public ?DateTime $approveDate = null;
}

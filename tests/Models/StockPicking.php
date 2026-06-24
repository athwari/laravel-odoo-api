<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Tests\Models;

use Athwari\LaravelOdooApi\Attributes\BelongsTo;
use Athwari\LaravelOdooApi\Attributes\Field;
use Athwari\LaravelOdooApi\Attributes\Model;
use Athwari\LaravelOdooApi\Odoo\OdooModel;

#[Model('stock.picking')]
class StockPicking extends OdooModel
{
    #[Field]
    public string $name;

    #[BelongsTo(name: 'partner_id', class: Partner::class)]
    public ?Partner $partner = null;

    #[Field('location_id')]
    public ?int $locationId = null;

    #[Field('location_dest_id')]
    public ?int $locationDestId = null;

    #[Field('picking_type_id')]
    public ?int $pickingTypeId = null;
}

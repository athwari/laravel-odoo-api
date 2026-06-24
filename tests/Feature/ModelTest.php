<?php

namespace Athwari\LaravelOdooApi\Tests\Feature;

use Athwari\LaravelOdooApi\Odoo\Casts\CastHandler;
use Athwari\LaravelOdooApi\Odoo\Casts\DateTimeCast;
use Athwari\LaravelOdooApi\Odoo\Models\LazyHasMany;
use Athwari\LaravelOdooApi\Odoo\OdooModel;
use Athwari\LaravelOdooApi\Tests\Models\Partner;
use Athwari\LaravelOdooApi\Tests\Models\Product;
use Athwari\LaravelOdooApi\Tests\Models\PurchaseOrder;
use Athwari\LaravelOdooApi\Tests\Models\PurchaseOrderLine;

beforeEach(function () {
    OdooModel::boot($this->odoo);
});

afterEach(function () {
    CastHandler::reset();
});

test('fields', function () {
    $fields = Partner::listFields();
    expect($fields)->toHaveProperty('name');
});

test('find', function () {
    $partner = Partner::find(1);
    expect($partner)->toBeInstanceOf(Partner::class);
    expect($partner->name)->not->toBeNull();
});

test('query', function () {
    $partner = new Partner();
    $partner->name = 'Azure Interior';
    $partner->save();

    $partner = Partner::query()
        ->where('name', '=', 'Azure Interior')
        ->first();

    expect($partner)->toBeInstanceOf(Partner::class);
    expect($partner->name)->toBe('Azure Interior');
});

test('create', function () {
    $partner = new Partner();
    $partner->name = 'Tester';
    $partner->save();

    expect($partner->id)->not->toBeNull();
});

test('readonly create', function () {
    $partner = new Partner();
    $partner->name = 'Tester';
    $partner->save();

    expect($partner->id)->not->toBeNull();
});

test('update', function () {
    $partner = new Partner();
    $partner->name = 'Tester';
    $partner->save();

    expect($partner->id)->not->toBeNull();
    $partner->name = 'Tester2';
    $partner->save();

    $check = Partner::find($partner->id);
    expect($check->name)->toBe('Tester2');
});

test('update null value', function () {
    $partner = new Partner();
    $partner->name = 'Tester';
    $partner->email = 'tester@example.org';
    $partner->save();

    expect($partner->id)->not->toBeNull();
    expect($partner->email)->not->toBeNull();

    $partner->name = 'Tester2';
    $partner->email = null;
    $partner->save();

    $check = Partner::find($partner->id);
    expect($check->name)->toBe('Tester2');
    expect($check->email)->toEqual(null);
});

test('select columns', function () {
    $items = Partner::query()->limit(5)
        ->fields(['display_name'])->get();

    expect($items)->not->toBeEmpty();
    expect(count($items))->toBeLessThanOrEqual(5);
    expect(isset($items[0]->name))->toBeFalse();
});

test('order by', function () {
    $items = Partner::query()->limit(5)
        ->orderBy('id', 'desc')
        ->fields(['name'])->get();

    expect($items)->toBeArray();
    expect(count($items))->toBeGreaterThanOrEqual(2);
    expect($items[0]->id)->toBeGreaterThan($items[1]->id);
});

test('belongs to', function () {
    $parent = new Partner();
    $parent->name = 'Parent';
    $parent->save();

    $child = new Partner();
    $child->parentId = $parent->id;

    expect($child->parent)->toBeInstanceOf(Partner::class);
    expect($child->parent->id)->toBe($parent->id);
});

test('has many create', function () {
    $partner = new Partner();
    $partner->name = 'Tester';
    $partner->save();

    $product = new Product();
    $product->name = 'Tester2';
    $product->save();

    $line = new PurchaseOrderLine();
    $line->name = 'Test';
    $line->productId = $product->id;
    $line->priceUnit = 10;
    $line->productQuantity = 1;

    $order = new PurchaseOrder();
    $order->partnerId = $partner->id;
    $order->lines = [$line];
    $order->save();

    expect($order->id)->not->toBeNull();
});

test('cast', function () {
    CastHandler::reset();
    CastHandler::registerCast(new DateTimeCast());

    $partner = new Partner();
    $partner->name = 'Test Partner';
    $partner->save();

    $order = new PurchaseOrder();
    $order->partnerId = $partner->id;
    $order->save();

    $item = PurchaseOrder::query()->where('id', '=', $order->id)->first();
    expect($item)->not->toBeNull();
    expect($item->orderDate)->not->toBeNull();
    expect($item->orderDate)->toBeInstanceOf(\DateTime::class);
});

test('nullable cast', function () {
    CastHandler::reset();
    CastHandler::registerCast(new DateTimeCast());

    $partner = new Partner();
    $partner->name = 'Test Partner';
    $partner->save();

    $order = new PurchaseOrder();
    $order->partnerId = $partner->id;
    $order->save();

    $item = PurchaseOrder::query()->where('id', '=', $order->id)->first();
    expect($item)->not->toBeNull();
    expect($item->approveDate)->toBeNull();
});

test('fill', function () {
    $partner = new Partner();
    $partner->fill([
        'name' => 'test',
    ]);

    expect($partner->name)->toBe('test');
});

test('equals', function () {
    $partner = new Partner();
    $partner->name = 'test';

    $partner2 = new Partner();
    $partner2->name = 'test';

    $partner3 = new Partner();
    $partner3->name = 'test';
    $partner3->email = 'test';

    $partner4 = new Partner();
    $partner4->name = 'test2';

    $partner5 = clone $partner;
    $partner6 = clone $partner;
    $partner6->name = 'some';

    expect($partner->equals($partner2))->toBeTrue();
    expect($partner->equals($partner3))->toBeFalse();
    expect($partner->equals($partner4))->toBeFalse();
    expect($partner->equals($partner5))->toBeTrue();
    expect($partner->equals($partner6))->toBeFalse();
});

test('has many relation hydration', function () {
    CastHandler::registerCast(new DateTimeCast());

    // 1. Set up a Partner for the PurchaseOrder
    $testPartner = new Partner();
    $testPartner->name = 'Test Partner for PO';
    $testPartner->save();
    expect($testPartner->id)->not->toBeNull('Failed to create test partner.');

    // 2. Set up a Product for PurchaseOrderLines
    $testProduct = new Product();
    $testProduct->name = 'Test Product for POLine';
    $testProduct->save();
    expect($testProduct->id)->not->toBeNull('Failed to create test product.');

    // 3. Create PurchaseOrder
    $order = new PurchaseOrder();
    $order->partnerId = $testPartner->id;
    $order->save();
    expect($order->id)->not->toBeNull('Failed to create purchase order.');

    // 4. Create PurchaseOrderLine instances
    $line1 = new PurchaseOrderLine();
    $line1->orderId = $order->id;
    $line1->name = 'Line 1';
    $line1->productId = $testProduct->id;
    $line1->productQuantity = 2;
    $line1->priceUnit = 10.0;
    $line1->save();
    expect($line1->id)->not->toBeNull('Failed to create purchase order line 1.');

    $line2 = new PurchaseOrderLine();
    $line2->orderId = $order->id;
    $line2->name = 'Line 2';
    $line2->productId = $testProduct->id;
    $line2->productQuantity = 5;
    $line2->priceUnit = 20.0;
    $line2->save();
    expect($line2->id)->not->toBeNull('Failed to create purchase order line 2.');

    // 5. Fetch the PurchaseOrder
    /** @var PurchaseOrder $fetchedOrder */
    $fetchedOrder = PurchaseOrder::find($order->id);
    expect($fetchedOrder)->not->toBeNull('Failed to fetch purchase order.');

    // 6. Assertions for the 'lines' property
    expect($fetchedOrder->lines)->toHaveCount(2, 'Should have 2 order lines.');

    foreach ($fetchedOrder->lines as $fetchedLine) {
        expect($fetchedLine)->toBeInstanceOf(PurchaseOrderLine::class, 'Each line should be an instance of PurchaseOrderLine.');
        expect($fetchedLine->id)->not->toBeNull('Fetched line should have an ID.');
        expect($fetchedLine->name)->not->toBeNull('Fetched line should have a name.');
        expect(in_array($fetchedLine->id, [$line1->id, $line2->id]))->toBeTrue();

        if ($fetchedLine->id === $line1->id) {
            expect($fetchedLine->productQuantity)->toBe($line1->productQuantity);
            expect($fetchedLine->priceUnit)->toBe($line1->priceUnit);
        } elseif ($fetchedLine->id === $line2->id) {
            expect($fetchedLine->productQuantity)->toBe($line2->productQuantity);
            expect($fetchedLine->priceUnit)->toBe($line2->priceUnit);
        }
    }
});

test('has many relation empty hydration', function () {
    CastHandler::registerCast(new DateTimeCast());

    // 1. Set up a Partner for the PurchaseOrder
    $testPartner = new Partner();
    $testPartner->name = 'Test Partner for Empty PO';
    $testPartner->save();
    expect($testPartner->id)->not->toBeNull('Failed to create test partner.');

    // 2. Create PurchaseOrder without lines
    $order = new PurchaseOrder();
    $order->partnerId = $testPartner->id;
    $order->save();
    expect($order->id)->not->toBeNull('Failed to create purchase order.');

    // 3. Fetch the PurchaseOrder
    /** @var PurchaseOrder $fetchedOrder */
    $fetchedOrder = PurchaseOrder::find($order->id);
    expect($fetchedOrder)->not->toBeNull('Failed to fetch purchase order.');

    // 4. Assert that the 'lines' property is a LazyHasMany instance and is empty
    expect($fetchedOrder->lines)->toBeInstanceOf(LazyHasMany::class, 'Order lines should be a LazyHasMany instance.');
    expect($fetchedOrder->lines)->toBeEmpty('Order lines property should be empty.');
});

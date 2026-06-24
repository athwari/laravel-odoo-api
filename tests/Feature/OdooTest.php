<?php

namespace Athwari\LaravelOdooApi\Tests\Feature;

use Athwari\LaravelOdooApi\Exceptions\AuthenticationException;
use Athwari\LaravelOdooApi\Odoo;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Domain;
use Athwari\LaravelOdooApi\Odoo\Request\Arguments\Options;
use Athwari\LaravelOdooApi\Odoo\Request\Request;

test('odoo authentication exception', function () {
    expect(fn () => (new Odoo(new Odoo\Config(
        $this->database,
        $this->host,
        $this->username,
        $this->password.'invalid'
    )))->connect())->toThrow(AuthenticationException::class);
});

test('version', function () {
    $version = $this->odoo->version();
    expect($version)->toBeInstanceOf(Odoo\Models\Version::class);
});

test('successful connection', function () {
    expect(gettype($this->odoo->getUid()))->toBe('integer');
});

test('check model access', function () {
    $check = $this->odoo->checkAccessRights('res.partner', 'read');
    expect($check)->toBeTrue();
});

test('check model access with builder', function () {
    $check = $this->odoo->model('res.partner')->can('read');
    expect($check)->toBeTrue();
});

test('direct count', function () {
    $amount = $this->odoo->count('res.partner');
    expect(gettype($amount))->toBe('integer');
});

test('direct count where', function () {
    $amount = $this->odoo->count('res.partner');

    $customerAmountDomain = (new Domain())->where('is_company', '=', true);
    $customerAmount = $this->odoo->count('res.partner', $customerAmountDomain);

    expect($customerAmount)->toBeLessThan($amount);
});

test('model count', function () {
    $amount = $this->odoo->model('res.partner')->count();
    expect(gettype($amount))->toBe('integer');
});

test('model count where', function () {
    $amount = $this->odoo->model('res.partner')->count();

    $customerAmount = $this->odoo
        ->model('res.partner')
        ->where('is_company', '=', true)
        ->count();

    expect($customerAmount)->toBeLessThan($amount);
});

test('search limit', function () {
    $ids = $this->odoo
        ->model('res.partner')
        ->limit(5)
        ->ids();

    expect($ids)->toBeArray();
});

test('read', function () {
    $ids = $this->odoo
        ->model('res.partner')
        ->limit(5)
        ->ids();

    $items = $this->odoo->read('res.partner', $ids);

    expect($items)->toBeArray();
    expect($items)->toHaveCount(5);
});

test('find', function () {
    $item = $this->odoo->find('res.partner', 2);
    expect($item)->toBeObject();
});

test('direct search read', function () {
    $items = $this->odoo->searchRead('res.partner', null, null, 0, 5);

    expect($items)->toBeArray();
    expect($items)->toHaveCount(5);
    expect($items[0]->name)->not->toBeNull();
});

test('direct search read fields', function () {
    $items = $this->odoo->searchRead('res.partner', null, ['name'], 0, 5);

    expect($items)->toBeArray();
    expect($items)->toHaveCount(5);
    expect($items[0]->email ?? null)->toBeNull();
});

test('model search read', function () {
    $items = $this->odoo
        ->model('res.partner')
        ->limit(5)
        ->get();

    expect($items)->toBeArray();
    expect($items)->toHaveCount(5);
    expect($items[0]->name)->not->toBeNull();
});

test('model search read fields', function () {
    $items = $this->odoo
        ->model('res.partner')
        ->fields(['name'])
        ->limit(5)
        ->get();

    expect($items)->toBeArray();
    expect($items)->toHaveCount(5);
    expect($items[0]->email ?? null)->toBeNull();
});

test('first', function () {
    $item = $this->odoo->model('res.partner')->first();
    expect($item->name)->not->toBeNull();
});

test('list fields', function () {
    $fields = $this->odoo->listModelFields('res.partner');
    expect($fields)->toBeObject();
});

test('create record', function () {
    $id = $this->odoo
        ->model('res.partner')
        ->create([
            'name' => 'Bobby Brown',
        ]);

    expect(gettype($id))->toBe('integer');
});

test('delete record', function () {
    $id = $this->odoo->create('res.partner', [
        'name' => 'Bobby Brown',
    ]);

    expect(gettype($id))->toBe('integer');

    $this->odoo->deleteById('res.partner', $id);

    $ids = $this->odoo
        ->model('res.partner')
        ->where('id', '=', $id)
        ->ids();

    expect($ids)->toBeEmpty();
});

test('delete search', function () {
    $id = $this->odoo->create('res.partner', [
        'name' => 'Bobby Brown',
    ]);

    expect(gettype($id))->toBe('integer');

    $deleteResponse = $this->odoo
        ->model('res.partner')
        ->where('name', '=', 'Bobby Brown')
        ->delete();

    expect($deleteResponse)->toBeTrue();

    $ids = $this->odoo
        ->model('res.partner')
        ->where('name', '=', 'Bobby Brown')
        ->ids();

    expect($ids)->toBeEmpty();
});

test('update by id', function () {
    $id = $this->odoo->create('res.partner', [
        'name' => 'Bobby Brown',
    ]);

    expect(gettype($id))->toBe('integer');

    $updateResponse = $this->odoo->updateById('res.partner', $id, [
        'name' => 'Dagobert Duck',
    ]);

    expect($updateResponse)->toBeTrue();

    $item = $this->odoo
        ->model('res.partner')
        ->where('id', '=', $id)
        ->fields(['name'])
        ->first();

    expect($item->name)->toBe('Dagobert Duck');
});

test('update search', function () {
    $id = $this->odoo->create('res.partner', [
        'name' => 'Bobby Brown',
    ]);

    expect(gettype($id))->toBe('integer');

    $updateResponse = $this->odoo
        ->model('res.partner')
        ->where('name', '=', 'Bobby Brown')
        ->update([
            'name' => 'Dagobert Duck',
        ]);

    expect($updateResponse)->toBeTrue();

    $ids = $this->odoo
        ->model('res.partner')
        ->where('name', '=', 'Bobby Brown')
        ->ids();

    expect($ids)->toBeEmpty();
});

test('call custom method', function () {
    $request = new class('res.partner', 'search') extends Request
    {
        public function toArray(): array
        {
            return [
                [
                    ['is_company', '=', false],
                ],
            ];
        }
    };
    $ids = $this->odoo->execute($request, new Options([
        'limit' => 3,
    ]));
    expect($ids)->toBeArray();
    expect($ids)->toHaveCount(3);
});

test('call custom method overlay', function () {
    $ids = $this->odoo->executeKw('res.partner', 'search', [
        [
            ['is_company', '=', false],
        ],
    ], new Options([
        'limit' => 3,
    ]));
    expect($ids)->toBeArray();
    expect($ids)->toHaveCount(3);
});

test('or', function () {
    $id = $this->odoo
        ->model('res.partner')
        ->create([
            'name' => 'Bobby Brown',
        ]);

    $id2 = $this->odoo
        ->model('res.partner')
        ->create([
            'name' => 'Gregor Green',
        ]);

    $ids = $this->odoo->model('res.partner')
        ->where('name', '=', 'Bobby Brown')
        ->orWhere('name', '=', 'Gregor Green')
        ->ids();

    expect(in_array($id, $ids))->toBeTrue();
    expect(in_array($id2, $ids))->toBeTrue();
});

test('aggregate', function () {
    $orderId = $this->odoo->model('sale.order')
        ->create([
            'name' => 'Aggregate Stuff',
            'partner_id' => 1,
        ]);

    $this->odoo->model('sale.order.line')
        ->create([
            'order_id' => $orderId,
            'product_id' => 1,
            'product_uom_qty' => 3,
        ]);

    $this->odoo->model('sale.order.line')
        ->create([
            'order_id' => $orderId,
            'product_id' => 1,
            'product_uom_qty' => 5,
        ]);

    $response = $this->odoo->model('sale.order.line')
        ->where('order_id', '=', $orderId)
        ->groupBy(['order_id'])
        ->fields(['product_uom_qty:sum'])
        ->get();

    expect($response[0]->order_id[0])->toBe($orderId);
    expect($response[0]->product_uom_qty)->toEqual(8);
});

test('authentication with fixed user id', function () {
    $fixedUserId = 1;
    $config = new Odoo\Config(
        $this->database,
        $this->host,
        'invalid-user',
        'invalid-pass',
        null,
        $fixedUserId
    );
    $odoo = new Odoo($config);
    $odoo->connect();
    expect($odoo->getUid())->toBe($fixedUserId);
});

test('authentication falls back to normal with null fixed user id', function () {
    $config = new Odoo\Config(
        $this->database,
        $this->host,
        $this->username,
        $this->password
    );
    $odoo = new Odoo($config);
    $odoo->connect();
    expect($odoo->getUid())->toBeInt();
    expect($odoo->getUid())->toBeGreaterThan(0);
});

test('authentication falls back to normal with zero fixed user id', function () {
    $config = new Odoo\Config(
        $this->database,
        $this->host,
        $this->username,
        $this->password,
        null,
        0
    );
    $odoo = new Odoo($config);
    $odoo->connect();
    expect($odoo->getUid())->toBeInt();
    expect($odoo->getUid())->toBeGreaterThan(0);
});

test('authentication falls back to normal with negative fixed user id', function () {
    $config = new Odoo\Config(
        $this->database,
        $this->host,
        $this->username,
        $this->password,
        null,
        -5
    );
    $odoo = new Odoo($config);
    $odoo->connect();
    expect($odoo->getUid())->toBeInt();
    expect($odoo->getUid())->toBeGreaterThan(0);
});

<?php

namespace Athwari\LaravelOdooApi\Tests\Unit;

use Athwari\LaravelOdooApi\Attributes\Field;
use Athwari\LaravelOdooApi\Attributes\HasMany;
use Athwari\LaravelOdooApi\Attributes\Key;
use Athwari\LaravelOdooApi\Attributes\Model;
use Athwari\LaravelOdooApi\Odoo\Models\LazyHasMany;
use Athwari\LaravelOdooApi\Odoo\OdooModel;

test('foreign key field dehydration', function () {
    // Create a test model that simulates the bug scenario
    $testModel = new class() extends OdooModel
    {
        #[Field, Key]
        public int|array $journal_id;

        #[Field('partner_id'), Key]
        public int $partner_id;

        #[Field]
        public ?string $name = null;

        #[HasMany(TestAccountMoveLine::class, 'line_ids')]
        public LazyHasMany|array $lines;
    };

    // Set up test data that mimics the bug scenario
    $testModel->journal_id = [1, 'Customer invoices']; // Array format from Odoo
    $testModel->partner_id = 683;
    $testModel->name = 'Test Invoice';

    // Create a LazyHasMany that hasn't been loaded (simulating unmodified relationships)
    $testModel->lines = new LazyHasMany(TestAccountMoveLine::class, [1, 2, 3]);

    // Dehydrate the model
    $dehydrated = $testModel->dehydrate($testModel);

    // Assert that journal_id is converted to integer (not array)
    expect($dehydrated->journal_id)->toBe(1);
    expect($dehydrated->journal_id)->toBeInt();

    // Assert that partner_id remains as integer
    expect($dehydrated->partner_id)->toBe(683);
    expect($dehydrated->partner_id)->toBeInt();

    // Assert that name is preserved
    expect($dehydrated->name)->toBe('Test Invoice');

    // Assert that unloaded LazyHasMany relationships are NOT included in dehydration
    expect(property_exists($dehydrated, 'line_ids'))->toBeFalse();
});

test('loaded relationship dehydration', function () {
    // Create a test model with loaded relationships
    $testModel = new class() extends OdooModel
    {
        #[Field, Key]
        public int|array $journal_id;

        #[HasMany(TestAccountMoveLine::class, 'line_ids')]
        public LazyHasMany|array $lines;
    };

    // Create test line items with foreign key arrays
    $line1 = new TestAccountMoveLine();
    $line1->id = 1;
    $line1->account_id = [316, '800100 Omzet NL handelsgoederen 1'];
    $line1->partner_id = [683, 'Test Partner'];
    $line1->name = 'Test Product 1';

    $line2 = new TestAccountMoveLine();
    $line2->id = 2;
    $line2->account_id = [73, '110000 Debiteuren'];
    $line2->partner_id = [683, 'Test Partner'];
    $line2->name = 'Test Product 2';

    $testModel->journal_id = [1, 'Customer invoices'];
    $testModel->lines = [$line1, $line2];

    // Dehydrate the model
    $dehydrated = $testModel->dehydrate($testModel);

    // Assert that journal_id is converted to integer
    expect($dehydrated->journal_id)->toBe(1);

    // Assert that line_ids contains proper update commands
    expect($dehydrated->line_ids)->toBeArray();
    expect($dehydrated->line_ids)->toHaveCount(2);

    // Check that each line command has proper structure [1, id, data]
    foreach ($dehydrated->line_ids as $command) {
        expect($command)->toBeArray();
        expect($command)->toHaveCount(3);
        expect($command[0])->toBe(1); // Update command
        expect($command[1])->toBeInt(); // Line ID
        expect($command[2])->toBeArray(); // Line data
        expect($command[2]['account_id'])->toBeInt();
        expect($command[2]['partner_id'])->toBeInt();
    }
});

test('empty relationship dehydration', function () {
    $testModel = new class() extends OdooModel
    {
        #[HasMany(TestAccountMoveLine::class, 'line_ids')]
        public ?array $lines = null;
    };
    $testModel->lines = null;

    $dehydrated = $testModel->dehydrate($testModel);

    // Assert that null relationships are not included
    expect(property_exists($dehydrated, 'line_ids'))->toBeFalse();
});

// Test model for the line items
#[Model('account.move.line')]
class TestAccountMoveLine extends OdooModel
{
    #[Field, Key]
    public int|array $account_id;

    #[Field, Key]
    public int|array $partner_id;

    #[Field]
    public string $name;
}

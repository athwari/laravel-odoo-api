# Athwari Laravel Odoo API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/athwari/laravel-odoo-api.svg?style=flat-square)](https://packagist.org/packages/athwari/laravel-odoo-api)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/athwari/laravel-odoo-api/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/athwari/laravel-odoo-api/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/athwari/laravel-odoo-api/fix-php-code-style.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/athwari/laravel-odoo-api/actions?query=workflow%3A"Fix+PHP+code+style"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/athwari/laravel-odoo-api?style=flat-square)](https://packagist.org/packages/athwari/laravel-odoo-api)

A robust, developer-friendly PHP JSON-RPC connector for Odoo, built natively for Laravel. It provides a fluent query builder, an attribute-based ORM model layer, and modern testing utilities.

---

## What's New in v2
Version 2 is a major architectural upgrade focusing on developer experience and performance:
- **Eager Loading**: Prevent N+1 issues with `->with('relation')` eager loading for BelongsTo and HasMany.
- **DTO Mapping**: Map raw Odoo responses directly into Data Transfer Objects using `->as(MyDTO::class)`.
- **Multi-Connection Support**: Manage multiple Odoo instances simultaneously via `Odoo::connection('erp')`.
- **Query Caching**: First-class caching support on the query builder using `->cache(ttl)`.
- **Batch Operations**: Bulk insert and update capabilities via `createMany()` and grouping-optimized `writeMany()`.
- **Native Testing**: Easily intercept requests and test logic offline using `Odoo::fake()`.
- **CLI Tooling**: Artisan commands to check config, ping the server, and discover Odoo model fields.
- **Resilient Transport**: Automatic retry middleware for connection drops and concurrent update deadlocks.

See [UPGRADE.md](UPGRADE.md) to migrate from v1, and [CHANGELOG.md](CHANGELOG.md) for full release history.

## Feature Matrix

| Feature | Support |
|---------|---------|
| Fluent Query Builder | ✅ |
| Chunking & Pagination | ✅ |
| Custom RPC Methods | ✅ |
| Attribute-Based Models | ✅ |
| BelongsTo / HasMany Relations | ✅ |
| Eager Loading | ✅ |
| Query Caching | ✅ |
| DTO Mapping | ✅ |
| Multiple Connections | ✅ |
| Native Test Mocking | ✅ |
| Odoo Version Feature-Flags | ✅ |

---

## Installation

```bash
composer require athwari/laravel-odoo-api
```

Publish the config file:

```bash
php artisan vendor:publish --tag=odoo-api-config
```

## Configuration

In your `.env` file, define your primary Odoo connection:

```env
ODOO_DATABASE=mycompany
ODOO_HOST=https://mycompany.odoo.com
ODOO_USERNAME=admin@mycompany.com
ODOO_PASSWORD=secret

# Optional: Use an API key instead of a password (Odoo 14+)
ODOO_API_KEY=

# Optional: Skip the authenticate() RPC call if you already know the User ID
ODOO_FIXED_USER_ID=

ODOO_TIMEOUT=30
ODOO_SSL_VERIFY=true

ODOO_LANG=en_US
ODOO_TIMEZONE=UTC
```

You can verify your configuration offline or ping the server via Artisan:

```bash
php artisan odoo:check-config
php artisan odoo:ping
```

---

## Multi-Connection Support

You can configure and interact with multiple Odoo servers/databases simultaneously. In your `config/odoo-api.php`:

```php
'connections' => [
    'default' => [
        'host' => env('ODOO_HOST'),
        // ...
    ],
    'staging' => [
        'host' => env('ODOO_STAGING_HOST'),
        // ...
    ],
]
```

Use `Odoo::connection()` to explicitly target a non-default connection:

```php
use Athwari\LaravelOdooApi\Facades\Odoo;

// Queries the 'staging' connection
$partners = Odoo::connection('staging')
    ->model('res.partner')
    ->limit(10)
    ->get();
```

---

## Querying

Interact with Odoo using the fluent query builder.

```php
use Athwari\LaravelOdooApi\Facades\Odoo;

// Basic Search & Read
$partners = Odoo::model('res.partner')
    ->where('active', '=', true)
    ->whereNot('is_company', true)
    ->orderBy('name')
    ->limit(20)
    ->get();

// Return an Illuminate Collection instead of a raw array
$collection = Odoo::model('res.partner')->collect();

// Count records
$count = Odoo::model('res.partner')->where('active', true)->count();
```

### Pagination and Chunking

For handling large datasets, the builder provides native pagination and chunking, keeping memory usage low.

```php
// LengthAwarePaginator
$paginated = Odoo::model('res.partner')->paginate(perPage: 15, page: 1);

// Chunk processing
Odoo::model('res.partner')
    ->where('active', true)
    ->chunk(100, function ($records) {
        foreach ($records as $record) {
            // Process 100 records at a time
        }
    });
```

### Caching

Avoid repeated network trips for expensive queries using the fluent cache method:

```php
// Cache the raw query response for 60 seconds. 
// Cache keys are automatically determined based on the query state.
$cachedPartners = Odoo::model('res.partner')
    ->where('is_company', true)
    ->cache(60)
    ->get();
```

### Create, Update, Delete

```php
// Create
$id = Odoo::create('res.partner', ['name' => 'Acme Corp']);

// Update (Requires a where clause for protection)
Odoo::model('res.partner')
    ->where('id', '=', $id)
    ->write(['phone' => '+1-555-0100']);

// Delete
Odoo::model('res.partner')
    ->where('active', false)
    ->delete();
```

> **Note**: Unscoped `update()` and `delete()` operations (calling them without any `where()` clauses) will throw an exception to prevent accidental bulk modifications.

### Batch Operations

```php
// Bulk Create: Send a single request for multiple creations. Returns an array of new IDs.
$ids = Odoo::model('res.partner')->createMany([
    ['name' => 'Partner A'],
    ['name' => 'Partner B']
]);

// Bulk Write: Automatically groups records receiving the identical payload into a single RPC write.
Odoo::model('res.partner')->writeMany([
    $id1 => ['active' => false],
    $id2 => ['active' => false], // Grouped with $id1
    $id3 => ['name' => 'New Name'] // Separate RPC call
]);
```

### Custom RPC Methods

Call arbitrary Odoo model methods effortlessly:

```php
$result = Odoo::executeKw('sale.order', 'action_confirm', [[$orderId]]);
```

---

## DTO Mapping

Map raw Odoo responses directly into strongly-typed Data Transfer Objects (DTOs) bypassing Eloquent-style hydration completely.

```php
class PartnerDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name
    ) {}
}

// Automatically maps each result into PartnerDTO
$dtos = Odoo::model('res.partner')
    ->where('active', true)
    ->as(PartnerDTO::class)
    ->get();
```
*Tip: If your DTO contains a static `fromArray(array $data): static` method, the builder will use that as a factory. Otherwise, it injects the array into the constructor.*

---

## Models

Define attribute-based models for a familiar ORM-like experience. Models encapsulate fields, casts, relationships, and connections.

```php
namespace App\Odoo;

use Athwari\LaravelOdooApi\Odoo\OdooModel;
use Athwari\LaravelOdooApi\Attributes\Model;
use Athwari\LaravelOdooApi\Attributes\Field;
use Athwari\LaravelOdooApi\Attributes\BelongsTo;
use Athwari\LaravelOdooApi\Attributes\HasMany;
use Athwari\LaravelOdooApi\Casts\DateTimeCast;

#[Model('res.partner')]
class Partner extends OdooModel
{
    // Specify non-default connections explicitly
    // protected string $connection = 'staging';

    #[Field]
    public int $id;

    #[Field]
    public string $name;

    #[Field('create_date', cast: DateTimeCast::class)]
    public ?\DateTimeImmutable $createdAt = null;

    #[BelongsTo('company_id', Company::class)]
    public ?Company $company = null;

    #[HasMany('child_ids', Partner::class)]
    public array $contacts = [];
}
```

### Interacting with Models

Models provide query builder proxies and handle lifecycle operations:

```php
// Read
$partner = Partner::query()->where('name', '=', 'Acme Corp')->first();
echo $partner->name;

// Insert
$newPartner = new Partner();
$newPartner->name = 'Jane Doe';
$newPartner->save();

// Update
$partner->name = 'Updated Name';
$partner->save();

// Delete
$partner->delete();
```

### Eager Loading

Prevent N+1 requests by eager loading relationships:

```php
$partners = Partner::query()->with(['company', 'contacts'])->get();

foreach ($partners as $partner) {
    // Relationships are already loaded in memory
    echo $partner->company?->name;
}
```

### Model Events

The `OdooModel` dispatches events during its lifecycle that you can hook into via Laravel:
- `Athwari\LaravelOdooApi\Events\OdooRecordCreated`
- `Athwari\LaravelOdooApi\Events\OdooRecordUpdated`
- `Athwari\LaravelOdooApi\Events\OdooRecordDeleted`

---

## Artisan Commands

Use the CLI to inspect Odoo and test connections:

```bash
# Check if your .env configuration has missing required keys
php artisan odoo:check-config

# Ping the Odoo server to verify authentication and connection
php artisan odoo:ping

# Discover all fields, types, and required statuses for a specific Odoo model
php artisan odoo:fields res.partner
```

---

## Testing

You can natively mock the transport layer to prevent real network calls during testing using `Odoo::fake()`.

```php
use Athwari\LaravelOdooApi\Facades\Odoo;

it('fetches active partners', function () {
    $fake = Odoo::fake();
    
    // Explicitly mock a method on a model and provide the response
    $fake->shouldReceive('res.partner', 'search_read')
         ->andReturn([['id' => 1, 'name' => 'Acme Corp']]);

    $partners = Odoo::model('res.partner')->get();

    expect($partners)->toHaveCount(1);
    
    // Assert expectations
    $fake->assertSent(fn ($model, $method) => $model === 'res.partner' && $method === 'search_read');
});
```

Because the fake is isolated to the application container, it will automatically clean up between your Laravel HTTP tests.

---

## Typed Exceptions

All exceptions thrown by the package extend `OdooException`.

- `AuthenticationException`: Invalid credentials, database, or API key.
- `ConnectionException`: Network issues, timeouts, or DNS failures.
- `AccessDeniedException`: Odoo rejected the operation due to permission/security rules.
- `RecordNotFoundException`: Attempted to update/delete a record ID that does not exist.
- `OdooModelException`: Odoo-side validation/business logic constraints failed.

## License

The MIT License (MIT).

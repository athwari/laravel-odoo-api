# athwari/laravel-odoo-api-odoo-api

[![Latest Version on Packagist](https://img.shields.io/packagist/v/athwari/laravel-odoo-api.svg?style=flat-square)](https://packagist.org/packages/athwari/laravel-odoo-api)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/athwari/laravel-odoo-api/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/athwari/laravel-odoo-api/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/athwari/laravel-odoo-api/fix-php-code-style.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/athwari/laravel-odoo-api/actions?query=workflow%3A"Fix+PHP+code+style"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/athwari/laravel-odoo-api?style=flat-square)](https://packagist.org/packages/athwari/laravel-odoo-api)

PHP Odoo JSON-RPC connector with an attribute-based model layer, prepared for laravel-odoo-api integration.

## Installation

```bash
composer require athwari/laravel-odoo-api-odoo-api
```

Publish the config:

```bash
php artisan vendor:publish --tag=odoo-api-config
```

## Configuration

`.env` keys:

```env
ODOO_DATABASE=mycompany
ODOO_HOST=https://mycompany.odoo.com
ODOO_USERNAME=admin@mycompany.com
ODOO_PASSWORD=secret

# Optional: API key instead of password (takes precedence when set, Odoo 14+)
ODOO_API_KEY=

# Optional: skip the authenticate() RPC call with a known static UID
ODOO_FIXED_USER_ID=

ODOO_TIMEOUT=30

# SSL certificate verification. Set to false ONLY for local dev with
# self-signed certificates — never disable in production.
ODOO_SSL_VERIFY=true

ODOO_LANG=en_US
ODOO_TIMEZONE=UTC
ODOO_COMPANY_ID=
```

## Basic usage

```php
use Athwari\laravel-odoo-apiOdooApi\Odoo;

$odoo = app(Odoo::class);   // resolved from the container in laravel-odoo-api

$partners = $odoo->model('res.partner')
    ->where('active', '=', true)
    ->orderBy('name')
    ->limit(20)
    ->get();

$id = $odoo->create('res.partner', ['name' => 'Acme Corp']);

$odoo->model('res.partner')
    ->where('id', '=', $id)
    ->write(['phone' => '+1-555-0100']);
```

Or via the Facade:

```php
use Athwari\laravel-odoo-apiOdooApi\Facades\Odoo;

Odoo::model('res.partner')->where('active', '=', true)->get();
```

### Custom / arbitrary Odoo methods

```php
$odoo->executeKw('sale.order', 'action_confirm', [[$orderId]]);
```

### Unscoped update/delete protection

```php
// Throws ValidationException — no where() means "every record in the model".
$odoo->model('res.partner')->delete();

// Correct:
$odoo->model('res.partner')->where('active', '=', false)->delete();
```

If you genuinely need to operate on all records, pass explicit IDs to the
underlying endpoint methods (`$odoo->unlink('res.partner', $ids)`), which
are not subject to this guard.

## Models

```php
use Athwari\laravel-odoo-apiOdooApi\Attributes\BelongsTo;
use Athwari\laravel-odoo-apiOdooApi\Attributes\Field;
use Athwari\laravel-odoo-apiOdooApi\Attributes\HasMany;
use Athwari\laravel-odoo-apiOdooApi\Attributes\Key;
use Athwari\laravel-odoo-apiOdooApi\Attributes\Model;
use Athwari\laravel-odoo-apiOdooApi\Odoo\Models\LazyHasMany;
use Athwari\laravel-odoo-apiOdooApi\Odoo\OdooModel;

#[Model('res.partner')]
class Partner extends OdooModel
{
    #[Field]
    public string $name;

    #[Field('email')]
    public ?string $email;

    #[Field('parent_id'), Key]
    public ?int $parentId;

    // BelongsTo resolves the related model eagerly at hydration time.
    #[Field('parent_id')]
    #[BelongsTo('parent_id', Partner::class)]
    public ?Partner $parent;

    // HasMany loads related records lazily on first access.
    #[Field('child_ids')]
    #[HasMany(Partner::class, 'child_ids')]
    public LazyHasMany $children;
}
```

```php
$partner = Partner::find(42);
$partner->parent?->name;              // eager BelongsTo
count($partner->children);            // lazy HasMany — fetched on first access
$partner->children->isLoaded();       // bool
$partner->children->reload();         // force re-fetch next access

$matches = Partner::query()->where('active', '=', true)->get();
$first   = Partner::query()->where('name', '=', 'Acme')->first();

$partner = new Partner();
$partner->name = 'New Co';
$partner->save();               // creates; $partner->id is set after
$partner->name = 'Renamed Co';
$partner->save();               // updates
```

`OdooModel::boot($odoo)` binds the Odoo client used by all model subclasses.
The service provider calls this automatically in laravel-odoo-api. For standalone use:

```php
$odoo = new Odoo(new Config($db, $host, $user, $pass));
OdooModel::boot($odoo);
```

**BelongsTo and recursion:** eager resolution is capped at a depth of 3 for
self-referential/cyclic relations (e.g. Partner → parent Partner). If you need
to walk a deep hierarchy, do it with explicit `find()` calls.

## Casts

Cast Odoo's raw field values to and from PHP types:

```php
use Athwari\laravel-odoo-apiOdooApi\Odoo\Casts\DateTimeCast;
use Athwari\laravel-odoo-apiOdooApi\Odoo\Casts\DateTimeImmutableCast;

// Basic UTC datetime cast
Odoo::registerCast(new DateTimeCast());

// DateTime shifted to a specific timezone on read
Odoo::registerCast(new DateTimeCast('Europe/Berlin'));
Odoo::registerCast(new DateTimeCast(new DateTimeZone('Asia/Dubai')));

// Immutable variant (DateTimeImmutable instead of DateTime)
Odoo::registerCast(new DateTimeImmutableCast());
Odoo::registerCast(new DateTimeImmutableCast('America/New_York'));
```

Once registered, the cast is applied automatically to every model property
type-hinted with the matching PHP type. Register casts before first use —
typically in a service provider's `boot()` method.

### Custom casts

```php
use Athwari\laravel-odoo-apiOdooApi\Odoo\Casts\Cast;

class MoneyCast extends Cast
{
    public function getType(): string { return Money::class; }
    public function cast(mixed $raw): ?Money { return $raw ? new Money($raw) : null; }
    public function uncast(mixed $value): mixed { return $value instanceof Money ? $value->amount : $value; }
}

Odoo::registerCast(new MoneyCast());
```

## Testing

```bash
composer test
```

To generate code coverage, Xdebug must be enabled for CLI. Run:

```bash
composer test-coverage
```

If Xdebug is installed but coverage still fails, ensure CLI mode is enabled:

```bash
php -d xdebug.mode=coverage vendor/bin/pest --coverage
```

The suite is fully offline — no Odoo instance is required. HTTP responses
are stubbed via Guzzle `MockHandler` injected through `Endpoint::setClient()`
and `Odoo::setObjectEndpoint()`.

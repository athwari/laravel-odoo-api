# Upgrade Guide

## Upgrading from v1 to v2

This package is a clean rebuild and continuation of `icons/laravel-odoo-api`. While most of the public API is preserved, there are some breaking changes related to namespaces, exception handling, and configuration.

### High Impact Changes

- **Namespace Change**: All classes have moved from `Icons\OdooApi\*` to `Athwari\LaravelOdooApi\*`. You must update all `use` statements in your application.
- **Exception Handling**:
  - `OdooException` constructor signature has changed to include structured fault data.
  - A new `ConnectionException` is thrown for transport-level failures.
  - Specific exceptions (`AccessDeniedException`, `RecordNotFoundException`, `ValidationException`) are now thrown for common Odoo errors.
- **Config File Cleanup**: Unused sections (`companies`, `users`, `api-keys`) were removed from `config/odoo-api.php`.

### Medium Impact Changes

- **Unscoped Update/Delete Guard**: Calling `update()` or `delete()` on a `RequestBuilder` without a `where()` condition will now throw a `ValidationException` to prevent accidental mass updates/deletions. If you genuinely need an unscoped operation, call the endpoint's `write()` or `unlink()` directly.
- **Strict Input Validation**: `create()` and `write()` now throw a `ValidationException` on empty data.

### Low Impact Changes

- **Removed OdooApiService**: The highly specific `OdooApiService.php` has been removed. If your application relied on it, you must copy its logic into your own application's codebase.
- **Sanitised Exception Messages**: Odoo tracebacks are no longer appended directly to exception messages to prevent log spam, but are still available via `$e->getFaultData()['debug']`.
- **Config Validation**: The package now logs a warning on boot if essential configuration values are missing, rather than failing silently.

---

### Step-by-Step Migration

#### 1. Update Namespaces

Search and replace the old namespace across your project:
- **Search**: `Icons\OdooApi`
- **Replace**: `Athwari\LaravelOdooApi`

#### 2. Update Exception Handling

If you previously caught `OdooException` expecting it to handle network failures, you should now also catch `ConnectionException`.

```php
// Before
try {
    $odoo->connect();
} catch (\Icons\OdooApi\Exceptions\OdooException $e) {
    // Handled both RPC and Network errors
}

// After
use Athwari\LaravelOdooApi\Exceptions\ConnectionException;
use Athwari\LaravelOdooApi\Exceptions\OdooException;

try {
    $odoo->connect();
} catch (ConnectionException $e) {
    // Handle network failures, malformed JSON, etc.
} catch (OdooException $e) {
    // Handle Odoo RPC errors
}
```

If you construct `OdooException` manually, update your call sites to use the new constructor:
`OdooException(string $message, int $code, ?Throwable $previous, array $faultData = [])`

#### 3. Review Unscoped Queries

Review your codebase for any queries that update or delete records without a `where()` clause.

```php
// Before
OdooModel::query()->delete(); // Proceeded, deleting all records

// After
OdooModel::query()->delete(); // Throws ValidationException

// If you really need to delete everything, you must specify the IDs:
OdooModel::query()->where('id', '>', 0)->delete(); 
```

#### 4. Update Configuration

Ensure your `config/odoo-api.php` does not rely on the removed `companies`, `users`, or `api-keys` arrays. If you published the configuration file previously, you should republish it or manually prune the unused sections.

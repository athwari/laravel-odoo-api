# Changelog

## [Unreleased]

### Added

- Added `UPGRADE.md` migration guide detailing changes from v1 to v2.
- Added boot-time configuration validation in `OdooApiServiceProvider` that logs a warning if essential Odoo config values are missing.
- Added `AccessDeniedException` and `RecordNotFoundException` to map `odoo.exceptions.AccessError` and `odoo.exceptions.MissingError` respectively.
- Added `whereNot()` method to the query builder to easily negate conditions.

### Changed

- Sanitised Odoo exception tracebacks by removing them from the exception message to prevent log spam, while keeping them truncated in the exception's fault data.
- Added explicit `@return \Illuminate\Support\Collection` PHPDoc type hint to `RequestBuilder::collect()` to improve static analysis and IDE autocomplete.

## 1.0.0 — Initial release

This package is a clean rebuild combining `icons/laravel-odoo-api`'s attribute-based ORM with hardening ported from old `icons/laravel-odoo-api`. If you're migrating from `icons/laravel-odoo-api`, read this in full before swapping packages — most of the public API is unchanged, but a few things genuinely differ.

### Namespace change

Every class moved from `Icons\OdooApi\*` to `Athwari\LaravelOdooApi\*`. This is a hard break — update your `use` statements. Method signatures on equivalent classes are otherwise unchanged unless noted below.

| Old | New |
|---|---|
| `Icons\OdooApi\Odoo` | `Athwari\LaravelOdooApi\Odoo` |
| `Icons\OdooApi\Odoo\OdooModel` | `Athwari\LaravelOdooApi\Odoo\OdooModel` |
| `Icons\OdooApi\Attributes\*` | `Athwari\LaravelOdooApi\Attributes\*` |
| `Icons\OdooApi\Exceptions\*` | `Athwari\LaravelOdooApi\Exceptions\*` |
| `Icons\OdooApi\Models\*` (bundled Partner/Product/SaleOrder/...) | `Athwari\LaravelOdooApi\Models\*` |

### What's preserved exactly

- `Odoo::find/search/read/searchRead/readGroup/create/write/updateById/unlink/deleteById/fieldsGet/listModelFields/checkAccessRights/can/model/connect/version` — same names, same signatures.
- `OdooModel::find/read/query/all/save/fill/equals/boot` — same names, same signatures.
- `RequestBuilder`'s fluent API: `where/orWhere/orderBy/limit/offset/fields/groupBy/get/first/ids/count/collect/create/write/update/delete`.
- `Domain`'s `where/orWhere/toArray/isEmpty/count` — flat AND/OR chaining only, same as before (no nested boolean trees in this release).
- `Context` (lang/timezone/companyId/contextArgs) — unchanged.
- Cast system (`Cast`, `CastHandler::registerCast`, `DateTimeCast`) — unchanged interface, one bug fix (see below).

### New (additive, non-breaking)

- **`executeKw()`** on `Odoo` and `ObjectEndpoint` — call arbitrary/custom Odoo methods that don't have a dedicated typed method.
- **API key authentication** — `Config` accepts an optional `apiKey` param; takes precedence over the password when set. Configure via `ODOO_API_KEY`.
- **Fixed user ID** — `Config` accepts an optional `fixedUserId` param that skips the `authenticate()` RPC call entirely. Configure via `ODOO_FIXED_USER_ID`.
- **Unscoped update/delete guard** — `RequestBuilder::update()`/`delete()` now throw `ValidationException` if no `where()` condition was set, preventing an accidental mass-write/delete across an entire model. Call the endpoint's `write()`/`unlink()` directly with explicit IDs if you genuinely need an unscoped operation.
- **Input validation** — `create()`/`write()` throw `ValidationException` on empty data; `executeKw()` throws on an empty model name. `read()`/`unlink()` short-circuit (return `[]`/`true`) on an empty ID list instead of making a wasted RPC call.
- **`#[BelongsTo]` is now functional** — previously an unused stub; now resolves the related model eagerly at hydration time. Capped by an internal recursion depth guard for self-referential/cyclic relations.
- **`#[HasMany]` is now lazily loaded** — backed by a new `LazyHasMany` collection (`ArrayAccess`, `Iterator`, `Countable`, `isLoaded()`, `reload()`). Previously, `#[HasMany]` only supported write-back; read access returned a raw array.
- **`Domain::addRaw()` and `Domain::make()`** — escape hatch for a raw domain criterion, and a static factory.
- A Laravel **Facade** (`Athwari\LaravelOdooApi\Facades\Odoo`) — not present in the previous package.
- **`Odoo::setObjectEndpoint()`** and **`Endpoint::setClient()`** — test-only seams to inject a mocked transport, bypassing real `connect()`/`authenticate()` calls.
- **`CastHandler::reset()`** — clears registered casts; useful for test isolation since cast registration is process-global static state.

### Breaking changes

- **`OdooException`'s constructor signature changed.** It previously carried a raw PSR `ResponseInterface`. It now carries structured fault data: `OdooException(string $message, int $code, ?Throwable $previous, array $faultData = [])`, with a new `getFaultData(): array` method. If you construct `OdooException` directly (uncommon — most code only catches it), update the call site. Catching it by type is unaffected.
- **New `ConnectionException`** sibling to `OdooException`, for transport-level failures (network/timeout/malformed response) as distinct from Odoo-side RPC errors. If you previously caught only `OdooException` expecting it to cover network failures too, add a catch for `ConnectionException` (both extend the same base, so a catch on a shared parent type still works if you introduce one, or catch both explicitly).
- **`config/odoo-api.php` no longer has a `companies`/`users`/`api-keys` block.** That data was never wired to anything functional in the previous package — its only consumer (`OdooApiService.php`) referenced classes that didn't exist anywhere in the codebase and could not have been instantiated. If you were relying on that config structure for your own code, it needs to be migrated separately; it was not a supported integration point.

### Removed

- **`OdooApiService.php`** — a ~176KB application-specific class (stock snapshots, product/UOM/currency catalogs, invoice/payment sync, attendance logging) that referenced `Icons\OdooApi\DataObjects\Rep` and multiple `Icons\OdooApi\Factories\*`/`Traits\*` classes that did not exist in the package. It was never bound in the service provider, never tested, and would fatal-error (`Class not found`) if instantiated. If any of this logic is still needed, it should live in your application code (or a separate, dedicated package), not in a generic Odoo API client.
- **`ConfigFactory.php`** — declared under the wrong namespace (a leftover from this package's `obuchmann/odoo-jsonrpc` origins), unreferenced anywhere in the codebase, not autoloadable as intended.
- Stray duplicate `Icons\Models\Partner.php` and backup files (`*.php_old`, `*.php_20240803`, `*.php_20260207`, `*.php_20260422`).
- Hardcoded API keys/hostnames previously present in `config/odoo-api.php`. **If that file was ever pushed to a shared/remote repository, rotate those keys in Odoo — do not assume this rebuild alone makes them safe.**

### Bug fixes

- `DateTimeCast::uncast()` previously had no `return` statement for non-`DateTime`, non-null input, silently discarding the value (returning `null`) instead of passing it through unchanged. Fixed, with a regression test.
- `JsonRpc\Client` previously only parsed the JSON-RPC error envelope when the HTTP status was exactly 200; any non-200 response threw a generic exception with no message extracted from the body, even when the body contained a perfectly parseable Odoo error. Now parses the error envelope regardless of HTTP status.
- `JsonRpc\Client` previously had no `json_last_error()` check after decoding the response body; a malformed/non-JSON body could produce confusing downstream errors instead of a clear exception. Now throws `ConnectionException` with a clear message.
- `JsonRpc\Client` previously did not capture or truncate `error.data.debug` (the Odoo traceback); large tracebacks could produce excessively long exception messages, and the debug info wasn't otherwise accessible. Now truncated to 500 characters and included in both the message and `getFaultData()`.
- `Context::toArray()` previously used PHP's default truthy `array_filter`, which would strip a legitimately-set `company_id => 0` or an empty-string context value. Now filters on `!== null` only.
- `Options::toArray()` previously called `$this->context->toArray()` unconditionally, which would fatal if `$context` was ever null (the constructor allows a null default). Now uses null-safe access.
- `CommonEndpoint::authenticate()` previously forced a brand-new real HTTP client on every call (`getClient(true)`), discarding any client injected via `setClient()` for testing. Fixed to reuse the existing/injected client.

### Testing

The test suite (`tests/`) is fully offline — every test stubs the HTTP layer via Guzzle's `MockHandler`. The previous package's test suite made a real network call to `https://demo.odoo.com/start` before every single test, which was slow, flaky, required network access, and could not exercise error paths deterministically. None of that remains; the new suite covers transport-level error handling, the domain builder, casts, the unscoped-write guard, and model hydration (including `#[BelongsTo]`/`#[HasMany]` and the recursion depth guard) without any external dependency.

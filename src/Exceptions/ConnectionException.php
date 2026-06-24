<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Exceptions;

/**
 * Thrown when the HTTP connection to Odoo fails, or the response
 * cannot be interpreted as JSON-RPC.
 *
 * Covers network timeouts, DNS resolution failures, TLS errors,
 * malformed/non-JSON response bodies, and any other transport-level
 * problem. Distinct from OdooException, which represents an error
 * returned *by* Odoo itself (a valid JSON-RPC error envelope).
 */
class ConnectionException extends OdooException {}

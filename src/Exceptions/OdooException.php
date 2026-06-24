<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for all Odoo API errors.
 *
 * Carries the raw Odoo fault payload (code/message/debug) when available,
 * so callers can inspect the original server-side error details
 * programmatically instead of parsing the message string.
 */
class OdooException extends RuntimeException
{
    /**
     * @param  string  $message  Human-readable error message
     * @param  int  $code  Exception code
     * @param  Throwable|null  $previous  Previous exception for chaining
     * @param  array  $faultData  Raw fault payload from Odoo (if any)
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        protected readonly array $faultData = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the raw fault data returned by the Odoo JSON-RPC endpoint.
     */
    public function getFaultData(): array
    {
        return $this->faultData;
    }
}

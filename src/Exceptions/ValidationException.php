<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Exceptions;

/**
 * Thrown when input validation fails before a request is sent to Odoo.
 *
 * For example: missing model name, empty data array for create/update,
 * or an unscoped update/delete attempted without a where() condition.
 */
class ValidationException extends OdooException {}

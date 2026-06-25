<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Exceptions;

/**
 * Thrown when Odoo denies access to a record or operation.
 * 
 * Typically corresponds to `odoo.exceptions.AccessError`.
 */
class AccessDeniedException extends OdooException {}

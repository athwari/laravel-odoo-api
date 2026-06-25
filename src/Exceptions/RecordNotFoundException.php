<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Exceptions;

/**
 * Thrown when an requested Odoo record does not exist.
 * 
 * Typically corresponds to `odoo.exceptions.MissingError`.
 */
class RecordNotFoundException extends OdooException {}

<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Exceptions;

use RuntimeException;

/**
 * Base exception for errors raised by the OdooModel layer
 * (e.g. a failed save() that did not throw a more specific exception).
 */
class OdooModelException extends RuntimeException {}

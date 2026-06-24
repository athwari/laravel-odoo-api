<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Exceptions;

use RuntimeException;

/**
 * Thrown for invalid or missing package configuration
 * (e.g. a required config key absent, or a Laravel-only
 * feature used outside of a Laravel application).
 */
class ConfigurationException extends RuntimeException {}

<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Exceptions;

/**
 * Thrown when Odoo authentication fails.
 *
 * Typically means the database name, username, password, or API key
 * provided in the configuration is incorrect, or the user account
 * is deactivated in Odoo.
 */
class AuthenticationException extends OdooException {}

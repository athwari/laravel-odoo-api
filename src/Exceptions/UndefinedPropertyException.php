<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Exceptions;

/**
 * Thrown by OdooModel::fill() when given a property name
 * that is not declared on the target model class.
 */
class UndefinedPropertyException extends OdooModelException {}

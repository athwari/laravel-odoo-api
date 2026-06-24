<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Athwari\LaravelOdooApi\Odoo connect(bool $force = false)
 * @method static \Athwari\LaravelOdooApi\Odoo\Request\RequestBuilder model(string $model, ?\Athwari\LaravelOdooApi\Odoo\Request\Arguments\Domain $domain = null)
 * @method static mixed executeKw(string $model, string $method, array $args = [], array $kwargs = [])
 * @method static array search(string $model, ?\Athwari\LaravelOdooApi\Odoo\Request\Arguments\Domain $domain = null, int $offset = 0, ?int $limit = null, ?string $order = null)
 * @method static array read(string $model, array $ids, array $fields = [])
 * @method static object|null find(string $model, int $id, array $fields = [])
 * @method static array searchRead(string $model, ?\Athwari\LaravelOdooApi\Odoo\Request\Arguments\Domain $domain = null, ?array $fields = null, int $offset = 0, ?int $limit = null, ?string $order = null)
 * @method static bool|int create(string $model, array $values)
 * @method static bool write(string $model, array $ids, array $values)
 * @method static bool unlink(string $model, array $ids)
 *
 * @see \Athwari\LaravelOdooApi\Odoo
 */
class Odoo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Athwari\LaravelOdooApi\Odoo::class;
    }
}

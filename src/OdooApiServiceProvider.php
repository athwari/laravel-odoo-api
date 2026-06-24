<?php

namespace Athwari\LaravelOdooApi;

use Athwari\LaravelOdooApi\Odoo\Config as OdooConfig;
use Athwari\LaravelOdooApi\Odoo\Context;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class OdooApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/odoo-api.php', 'odoo-api');

        $this->app->singleton(Odoo::class, function () {
            $config = new OdooConfig(
                database: (string) Config::get('odoo-api.database'),
                host: (string) Config::get('odoo-api.host'),
                username: (string) Config::get('odoo-api.username'),
                password: (string) Config::get('odoo-api.password'),
                apiKey: Config::get('odoo-api.api_key') ?: null,
                fixedUserId: Config::get('odoo-api.fixed_user_id') !== null
                    ? (int) Config::get('odoo-api.fixed_user_id')
                    : null,
                timeout: (int) Config::get('odoo-api.timeout', 30),
                sslVerify: (bool) Config::get('odoo-api.ssl_verify', true),
            );

            $context = new Context(
                lang: (string) Config::get('odoo-api.context.lang'),
                timezone: (string) Config::get('odoo-api.context.timezone'),
                companyId: Config::get('odoo-api.context.company_id') !== null
                    ? (int) Config::get('odoo-api.context.company_id')
                    : null,
            );

            $odoo = new Odoo($config, $context);

            Odoo\OdooModel::boot($odoo);

            return $odoo;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/odoo-api.php' => $this->app->configPath('odoo-api.php'),
            ], 'odoo-api-config');
        }
    }
}

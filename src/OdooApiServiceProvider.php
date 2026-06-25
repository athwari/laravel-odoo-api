<?php

namespace Athwari\LaravelOdooApi;

use Athwari\LaravelOdooApi\Odoo\Config as OdooConfig;
use Athwari\LaravelOdooApi\Odoo\Context;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
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

        $this->validateConfig();
    }

    protected function validateConfig(): void
    {
        $host = Config::get('odoo-api.host');
        $database = Config::get('odoo-api.database');
        $username = Config::get('odoo-api.username');
        $password = Config::get('odoo-api.password');
        $apiKey = Config::get('odoo-api.api_key');

        if (empty($host) || empty($database) || empty($username) || (empty($password) && empty($apiKey))) {
            Log::warning('Odoo API configuration is incomplete. Essential values (host, database, username, password/api_key) must be set in config/odoo-api.php or .env.');
        }
    }
}

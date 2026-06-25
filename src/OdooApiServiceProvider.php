<?php

namespace Athwari\LaravelOdooApi;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class OdooApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/odoo-api.php', 'odoo-api');

        $this->app->singleton(OdooManager::class, function ($app) {
            return new OdooManager($app);
        });

        $this->app->singleton(Odoo::class, function ($app) {
            return $app->make(OdooManager::class)->connection();
        });

        // Ensure OdooModel uses the Manager for resolving connections.
        \Athwari\LaravelOdooApi\Odoo\OdooModel::setConnectionResolver($this->app->make(OdooManager::class));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/odoo-api.php' => $this->app->configPath('odoo-api.php'),
            ], 'odoo-api-config');

            $this->commands([
                \Athwari\LaravelOdooApi\Commands\PingCommand::class,
                \Athwari\LaravelOdooApi\Commands\FieldsCommand::class,
                \Athwari\LaravelOdooApi\Commands\CheckConfigCommand::class,
            ]);
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

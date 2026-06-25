<?php

namespace Athwari\LaravelOdooApi\Commands;

use Athwari\LaravelOdooApi\Exceptions\AuthenticationException;
use Athwari\LaravelOdooApi\Exceptions\ConnectionException;
use Athwari\LaravelOdooApi\Odoo;
use Illuminate\Console\Command;
use Throwable;

class PingCommand extends Command
{
    protected $signature = 'odoo:ping';

    protected $description = 'Ping the Odoo server to verify connection and authentication.';

    public function handle(Odoo $odoo): int
    {
        $this->info('Pinging Odoo server...');

        $config = $odoo->getConfig();
        $this->line("Host: <comment>{$config->getHost()}</comment>");
        $this->line("Database: <comment>{$config->getDatabase()}</comment>");
        $this->line("Username: <comment>{$config->getUsername()}</comment>");

        if ($config->getApiKey() !== null) {
            $this->line('Auth Mode: <comment>API Key</comment>');
        } elseif ($config->getFixedUserId() !== null) {
            $this->line("Auth Mode: <comment>Fixed User ID ({$config->getFixedUserId()})</comment>");
        } else {
            $this->line('Auth Mode: <comment>Password</comment>');
        }

        try {
            $version = $odoo->version();
            $this->line("Server Version: <info>{$version->serverVersion}</info>");

            $this->line('Connecting and Authenticating');
            $odoo->connect(true);

            $this->info('Successfully connected to Odoo!');

            return self::SUCCESS;
        } catch (AuthenticationException $e) {
            $this->error('Authentication failed: '.$e->getMessage());

            return self::FAILURE;
        } catch (ConnectionException $e) {
            $this->error('Connection failed: '.$e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('An unexpected error occurred: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

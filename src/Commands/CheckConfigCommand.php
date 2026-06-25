<?php

namespace Athwari\LaravelOdooApi\Commands;

use Athwari\LaravelOdooApi\Odoo;
use Illuminate\Console\Command;

class CheckConfigCommand extends Command
{
    protected $signature = 'odoo:check-config';

    protected $description = 'Validate the local Odoo configuration.';

    public function handle(Odoo $odoo): int
    {
        $config = $odoo->getConfig();
        $hasErrors = false;

        $this->info('Checking Odoo Configuration');

        // Check Host
        $host = $config->getHost();
        if (empty($host)) {
            $this->error('Host: Missing');
            $hasErrors = true;
        } elseif (! filter_var($host, FILTER_VALIDATE_URL)) {
            $this->error("Host: Invalid URL format ({$host})");
            $hasErrors = true;
        } else {
            $this->line('Host configured');
        }

        // Check Database
        $database = $config->getDatabase();
        if (empty($database)) {
            $this->error('Database: Missing');
            $hasErrors = true;
        } else {
            $this->line('Database configured');
        }

        // Check Username
        $username = $config->getUsername();
        if (empty($username)) {
            $this->error('Username: Missing');
            $hasErrors = true;
        } else {
            $this->line('Username configured');
        }

        // Check Authentication / Credentials
        if ($config->isUsingApiKey()) {
            $this->line('Authentication: API Key');
        } elseif (! empty($config->getPassword())) {
            $this->line('Authentication: Password');
        } elseif ($config->hasFixedUserId()) {
            $this->line('Authentication: Bypassed (Fixed User ID)');
        } else {
            $this->error('Authentication: Missing Password or API Key');
            $hasErrors = true;
        }

        // Check Fixed User ID
        if ($config->hasFixedUserId()) {
            $this->line("Fixed User ID: {$config->getFixedUserId()}");
        }

        // Check SSL Verify
        $sslStatus = $config->isSslVerify() ? 'Enabled' : 'Disabled';
        $this->line("SSL Verification: {$sslStatus}");

        if ($hasErrors) {
            $this->error('Configuration validation failed. Please check your .env or config/odoo.php file.');

            return self::FAILURE;
        }

        $this->info('Configuration is complete and well-formed.');

        return self::SUCCESS;
    }
}

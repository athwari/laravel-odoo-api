<?php

namespace Athwari\LaravelOdooApi\Tests\Integration;

use Athwari\LaravelOdooApi\Odoo;
use Athwari\LaravelOdooApi\Odoo\Config;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class IntegrationTestCase extends BaseTestCase
{
    protected Odoo $odoo;

    protected string $host;

    protected string $database;

    protected string $username;

    protected string $password;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDemoCredentials();
        $this->odoo = new Odoo(new Config($this->database, $this->host, $this->username, $this->password));
        $this->odoo->connect();
    }

    protected function setDemoCredentials(): void
    {
        $this->host = getenv('ODOO_HOST') ?: 'http://localhost:8069/';
        $this->username = getenv('ODOO_USERNAME') ?: 'admin';
        $this->password = getenv('ODOO_PASSWORD') ?: 'admin';
        $this->database = getenv('ODOO_DATABASE') ?: 'odoo';
    }
}

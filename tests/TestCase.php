<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        Config::set('database.default', env('DB_CONNECTION', 'mysql'));
        DB::purge('tenant');

        parent::tearDown();
    }

    protected function createIsolatedDatabase(string $database): void
    {
        $this->databaseAdminConnection()->statement(sprintf(
            'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            str_replace('`', '``', $database)
        ));

        DB::purge('tenant_admin');
    }

    protected function dropIsolatedDatabase(string $database): void
    {
        $this->databaseAdminConnection()->statement(sprintf(
            'DROP DATABASE IF EXISTS `%s`',
            str_replace('`', '``', $database)
        ));

        DB::purge('tenant_admin');
    }

    protected function dropTestTenantDatabases(): void
    {
        foreach ($this->databaseAdminConnection()->select("SHOW DATABASES LIKE 'test\\_tenant\\_%'") as $row) {
            $database = array_values((array) $row)[0];

            $this->dropIsolatedDatabase($database);
        }
    }

    private function databaseAdminConnection(): \Illuminate\Database\ConnectionInterface
    {
        Config::set('database.connections.tenant_admin', array_replace(
            Config::get('database.connections.mysql', []),
            ['database' => null]
        ));

        DB::purge('tenant_admin');

        return DB::connection('tenant_admin');
    }
}

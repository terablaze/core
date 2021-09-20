<?php

namespace TeraBlaze\Database\Migrations;

use TeraBlaze\Database\Connection\ConnectionInterface;
use TeraBlaze\Database\Schema\SchemaBuilderInterface;

abstract class Migration
{
    /**
     * The name of the database connection to use.
     *
     * @var string|null
     */
    protected $connectionName = null;

    /**
     * Get the migration connection name.
     *
     * @return string|null
     */
    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    public function getConnection(): ConnectionInterface
    {
        $connString = $this->getConnectionName()
            ? 'database.connection.' . $this->getConnectionName()
            : ConnectionInterface::class;

        return container()->get($connString);
    }

    public function createTable(string $table): SchemaBuilderInterface
    {
        return $this->getConnection()->createTable($table);
    }

    public function alterTable(string $table): SchemaBuilderInterface
    {
        return $this->getConnection()->alterTable($table);
    }

    public function dropTable(string $table): SchemaBuilderInterface
    {
        return $this->getConnection()->dropTable($table);
    }

    public function dropTableIfExists(string $table): SchemaBuilderInterface
    {
        return $this->getConnection()->dropTableIfExists($table);
    }

    public function up(): void {}

    public function down(): void {}
}

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
    final public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    final public function getConnection(): ConnectionInterface
    {
        $connString = $this->getConnectionName()
            ? 'database.connection.' . $this->getConnectionName()
            : ConnectionInterface::class;

        return container()->get($connString);
    }

    final public function createTable(string $table): SchemaBuilderInterface
    {
        return $this->getConnection()->createTable($table);
    }

    final public function alterTable(string $table): SchemaBuilderInterface
    {
        return $this->getConnection()->alterTable($table);
    }

    final public function dropTable(string $table): SchemaBuilderInterface
    {
        return $this->getConnection()->dropTable($table);
    }

    final public function dropTableIfExists(string $table): SchemaBuilderInterface
    {
        return $this->getConnection()->dropTableIfExists($table);
    }

    final public function renameTable(string $from, string $to): SchemaBuilderInterface
    {
        return $this->getConnection()->renameTable($from, $to);
    }

    public function up(): void {}

    public function down(): void {}
}

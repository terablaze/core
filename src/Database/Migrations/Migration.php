<?php

namespace TeraBlaze\Database\Migrations;

use TeraBlaze\Database\Connection\ConnectionInterface;
use TeraBlaze\Database\Schema\SchemaInterface;

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

    final public function createTable(string $table): SchemaInterface
    {
        return $this->getConnection()->createTable($table);
    }

    final public function alterTable(string $table): SchemaInterface
    {
        return $this->getConnection()->alterTable($table);
    }

    final public function dropTable(string $table): SchemaInterface
    {
        $this->getConnection()->disableForeignKeyConstraints();
        return $this->getConnection()->dropTable($table);
    }

    final public function dropTableIfExists(string $table): SchemaInterface
    {
        $this->getConnection()->disableForeignKeyConstraints();
        return $this->getConnection()->dropTableIfExists($table);
    }

    final public function renameTable(string $from, string $to): SchemaInterface
    {
        return $this->getConnection()->renameTable($from, $to);
    }

    public function up(): void
    {
    }

    public function down(): void
    {
    }
}

<?php

namespace TeraBlaze\Database\Migrations;

use TeraBlaze\Database\Connection\ConnectionInterface;

abstract class Migration
{
    /**
     * The name of the database connection to use.
     *
     * @var string|null
     */
    protected ?string $connectionName = null;

    /**
     * Get the migration connection name.
     *
     * @return string|null
     */
    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    public function up(ConnectionInterface $connection): void {}

    public function down(ConnectionInterface $connection): void {}
}

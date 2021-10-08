<?php

namespace TeraBlaze\Database\Schema;

use TeraBlaze\Database\Exception\MigrationException;
use TeraBlaze\Database\Schema\Builder\SqliteBuilder;

class SqliteSchema extends AbstractSchema
{
    public function dropColumn($column = null): self
    {
        throw new MigrationException("SQLite doesn't support dropping columns");
    }

    public function build(): void
    {
        (new SqliteBuilder($this))->build();
    }
}

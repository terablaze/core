<?php

namespace TeraBlaze\Database\Schema;

use TeraBlaze\Database\Exception\MigrationException;
use TeraBlaze\Database\Schema\Builder\SqliteBuilder;

class SqliteSchema extends AbstractSchema
{
    public function build(): void
    {
        (new SqliteBuilder($this))->build();
    }
}

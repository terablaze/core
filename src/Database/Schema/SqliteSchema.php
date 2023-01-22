<?php

namespace Terablaze\Database\Schema;

use Terablaze\Database\Exception\MigrationException;
use Terablaze\Database\Schema\Builder\SqliteBuilder;

class SqliteSchema extends AbstractSchema
{
    public function build(): void
    {
        (new SqliteBuilder($this))->build();
    }
}

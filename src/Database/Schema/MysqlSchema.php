<?php

namespace Terablaze\Database\Schema;

use Terablaze\Database\Schema\Builder\MysqlBuilder;

class MysqlSchema extends AbstractSchema
{
    public function build(): void
    {
        (new MysqlBuilder($this))->build();
    }
}

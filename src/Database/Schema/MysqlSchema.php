<?php

namespace TeraBlaze\Database\Schema;

use TeraBlaze\Database\Schema\Builder\MysqlBuilder;

class MysqlSchema extends AbstractSchema
{
    public function build(): void
    {
        (new MysqlBuilder($this))->build();
    }
}

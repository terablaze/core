<?php

namespace TeraBlaze\Database\ORM;

use TeraBlaze\Database\Connection\ConnectionInterface;

interface ModelInterface
{
    /**
     * @param int|string $modelId
     * @return static|null
     */
    public static function find($modelId): ?self;
}

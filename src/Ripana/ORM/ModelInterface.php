<?php

namespace TeraBlaze\Ripana\ORM;

use TeraBlaze\Ripana\Database\Connection\ConnectionInterface;

interface ModelInterface
{
    /**
     * @param int|string $modelId
     * @return static|null
     */
    public static function find($modelId): ?self;
}
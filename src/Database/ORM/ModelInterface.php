<?php

namespace Terablaze\Database\ORM;

interface ModelInterface
{
    public const DATE_TYPES = ['date', 'time', 'datetime', 'timestamp', 'year'];

    /**
     * @param int|string $modelId
     * @return $this|null
     */
    public static function find($modelId);
}

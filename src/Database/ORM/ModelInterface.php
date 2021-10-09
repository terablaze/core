<?php

namespace TeraBlaze\Database\ORM;

interface ModelInterface
{
    public const DATA_TYPES = [
        'autonumber' => 'autonumber',
        'text' => 'text',
        'integer' => 'integer',
        'decimal' => 'decimal',
        'boolean' => 'boolean',
        'bool' => 'bool',
        'datetime' => 'datetime',
    ];

    public const DATE_TYPES = ['date', 'time', 'datetime', 'timestamp', 'year'];

    /**
     * @param int|string $modelId
     * @return static|null
     */
    public static function find($modelId): ?self;
}

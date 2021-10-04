<?php

namespace TeraBlaze\Database\ORM;

use TeraBlaze\Database\Connection\ConnectionInterface;

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

    public const truesy = [
        1, "1", "true", true, "yes", "yeah", "yup", "yupp", "y"
    ];
    public const falsy = [
        0, "0", "false", false, "no", "nope", "nah", "neh", "n"
    ];
    /**
     * @param int|string $modelId
     * @return static|null
     */
    public static function find($modelId): ?self;
}

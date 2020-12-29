<?php

namespace TeraBlaze\Ripana\Logging;

use function microtime;

/**
 * Includes executed SQLs in a Query Logger.
 */
class QueryLogger
{
    /**
     * Executed SQL queries.
     *
     * @var array<int, array<string, mixed>>
     */
    public $queries = [];

    /**
     * If Debug Stack is enabled (log queries) or not.
     *
     * @var bool
     */
    public $enabled = true;

    /** @var float|null */
    public $start = null;

    /** @var int */
    public $currentQuery = 0;

    /**
     * Logs a SQL statement somewhere.
     *
     * @param string $sql SQL statement
     * @return void
     */
    public function startLog(string $sql)
    {
        if (!$this->enabled) {
            return;
        }

        $this->start = microtime(true);

        $this->queries[++$this->currentQuery] = [
            'sql' => $sql,
            'executionTime' => 0,
        ];
    }

    /**
     * Marks the last started query as stopped. This can be used for timing of queries.
     *
     * @param int $affectedRows
     * @return void
     */
    public function stopLog(int $affectedRows)
    {
        if (!$this->enabled) {
            return;
        }

        $this->queries[$this->currentQuery]['affectedRows'] = $affectedRows;
        $this->queries[$this->currentQuery]['executionTime'] = microtime(true) - $this->start;
    }
}

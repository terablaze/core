<?php

namespace TeraBlaze\Ripana\Logging;

use Exception;

use function microtime;

/**
 * Includes executed SQLs in a Query Logger.
 */
class QueryLogger
{
    /**
     * Executed SQL queries.
     *
     * @var LoggedQuery[]
     */
    public array $queries = [];

    /**
     * If Debug Stack is enabled (log queries) or not.
     *
     * @var bool
     */
    public bool $enabled = true;

    /** @var int */
    public int $currentQuery = 0;

    /**
     * Logs a SQL statement somewhere.
     * @param string $sql SQL Statement
     * @param array<string, mixed> $params
     */
    public function startLog(string $sql, array $params = [], $startTime = null, $startMemory = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $loggedQuery = new LoggedQuery($sql, $params);
        $loggedQuery->start($startTime, $startMemory);
        $this->queries[++$this->currentQuery] = $loggedQuery;
    }

    /**
     * Marks the last started query as stopped. This can be used for timing of queries.
     *
     * @param Exception|null $exception
     * @param int $affectedRows
     * @param mixed $memoryEnd
     * @return void
     */
    public function stopLog(int $affectedRows, $memoryEnd = null): void
    {
        $this->queries[$this->currentQuery]->end(null, $affectedRows, $memoryEnd);
    }

    /**
     * Marks the last started query as stopped. This can be used for timing of queries.
     *
     * @param Exception|null $exception
     * @param int $affectedRows
     * @param mixed $memoryEnd
     * @return void
     */
    public function stopLogForFailed(Exception $exception, $memoryEnd = null): void
    {
        $this->queries[$this->currentQuery]->end($exception, $memoryEnd);
    }
}

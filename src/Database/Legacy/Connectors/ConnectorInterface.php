<?php

namespace TeraBlaze\Database\Legacy\Connectors;

use mysqli_result;
use TeraBlaze\Database\Exception\ServiceException;
use TeraBlaze\Database\Legacy\Query\QueryInterface;
use TeraBlaze\Database\Logging\QueryLogger;

/**
 * Interface ConnectorInterface
 * @package TeraBlaze\Database\Database
 */
interface ConnectorInterface
{
    public const SQL_FUNCTIONS = [
        'NOW()',
    ];

    public function setName(string $cname): self;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * connects to the database
     */
    public function connect(): ConnectorInterface;

    /**
     * disconnects from the database
     */
    public function disconnect(): ConnectorInterface;

    /**
     * returns a corresponding query instance
     */
    public function query(): QueryInterface;

    /**
     * Executes the provided SQL statement
     * @param string $sql
     * @param string|bool $dumpSql
     * @return bool|mysqli_result
     * @throws ServiceException
     */
    public function execute(string $sql, $dumpSql = '');

    /**
     * escapes the provided value to make it safe for queries
     * @param string $value
     * @return string
     */
    public function escape(string $value): string;

    /**
     * returns the ID of the last row
     * to be inserted
     */
    public function getLastInsertId();

    /**
     * returns the number of rows affected
     * by the last SQL query executed
     * @return int
     */
    public function getAffectedRows(): int;

    /**
     * returns the last error that occurred
     * @return string
     */
    public function getLastError(): string;

    /**
     * Constructs the sql query to create a database table
     * based on the passed model class
     * @param string $modelClass
     * @return string[]
     */
    public function buildSyncSQL(string $modelClass): array;

    /**
     * Creates a table in the database using the passed model
     * @param string $model
     * @return ConnectorInterface
     */
    public function sync(string $model): ConnectorInterface;

    /**
     * Returns the configured dateTimeMode [DATETIME or TIMESTAMP]
     * or returns DATETIME by default
     * @return string
     */
    public function getDateTimeMode(): string;

    public function getQueryLogger(): QueryLogger;
}
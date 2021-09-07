<?php

namespace TeraBlaze\Ripana\Database\Connection;

use PDO;
use PDOStatement;
use TeraBlaze\Ripana\Migration\Migration;
use TeraBlaze\Ripana\Database\QueryBuilder\Expression\ExpressionBuilder;
use TeraBlaze\Ripana\Database\QueryBuilder\QueryBuilderInterface;
use TeraBlaze\Ripana\Logging\QueryLogger;

interface ConnectionInterface
{
    public const SQL_FUNCTIONS = [
        'NOW()',
    ];

    public function setDatabaseConfName(string $dbConfName): ConnectionInterface;

    /**
     * @return string
     */
    public function getDatabaseConfName(): string;

//    /**
//     * connects to the database
//     */
//    public function connect(): ConnectionInterface;
//
//    /**
//     * disconnects from the database
//     */
//    public function disconnect(): ConnectionInterface;
    /**
     * Get the underlying PDO instance for this connection
     */
    public function pdo(): PDO;

    /**
     * Get the ID of the last row that was inserted
     */
    public function getLastInsertId(): string;

    public function execute($sql, array $params = []);

    public function getExpressionBuilder(): ExpressionBuilder;

    /**
     * Start a new query on this connection
     */
    public function query(): QueryBuilderInterface;

    /**
     * Start a new query on this connection
     */
    public function getQueryBuilder(): QueryBuilderInterface;

    /**
     * Start a new migration to add a table on this connection
     */
    public function createTable(string $table): Migration;

    /**
     * Start a new migration to add a table on this connection
     */
    public function alterTable(string $table): Migration;

    /**
     * Return a list of table names on this connection
     */
    public function getTables(): array;

    /**
     * Find out if a table exists on this connection
     */
    public function hasTable(string $name): bool;

    /**
     * Drop all tables in the current database
     */
    public function dropTables(): int;

    public function escape($value);

    /**
     * Returns the configured dateTimeMode [DATETIME or TIMESTAMP]
     * or returns DATETIME by default
     * @return string
     */
    public function getDateTimeMode(): string;

    public function setQueryLogger(QueryLogger $queryLogger): self;

    public function getQueryLogger(): QueryLogger;
}

<?php

namespace TeraBlaze\Database\Connection;

use Closure;
use PDO;
use TeraBlaze\Database\Query\Expression\ExpressionBuilder;
use TeraBlaze\Database\Query\QueryBuilderInterface;
use TeraBlaze\Database\Logging\QueryLogger;
use TeraBlaze\Database\Schema\SchemaInterface;
use TeraBlaze\EventDispatcher\Dispatcher;

interface ConnectionInterface
{
    public const SQL_FUNCTIONS = [
        'NOW()',
    ];

    public function setName(string $name): self;

    public function getName(): string;

    public function getMigrationsTable(): string;

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
     * @return SchemaInterface
     */
    public function getSchema(string $table): SchemaInterface;

    /**
     * Start a new query on this connection
     */
    public function getQueryBuilder(): QueryBuilderInterface;

    /**
     * Start a new migration to add a table on this connection
     */
    public function createTable(string $table): SchemaInterface;

    /**
     * Start a new migration to add a table on this connection
     */
    public function alterTable(string $table): SchemaInterface;

    /**
     * Start a new migration to drop a table from this connection
     */
    public function dropTable(string $table): SchemaInterface;

    /**
     * Start a new migration to drop a table if exists from this connection
     */
    public function dropTableIfExists(string $table): SchemaInterface;

    /**
     * Start a new migration to drop a table if exists from this connection
     */
    public function renameTable(string $from, string $to): SchemaInterface;

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

    public function quote($value, $type = PDO::PARAM_STR);

    public function escape($value, $type = PDO::PARAM_STR);

    public function setQueryLogger(QueryLogger $queryLogger): self;

    public function getQueryLogger(): QueryLogger;

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param Closure $callback
     * @return array
     */
    public function pretend(Closure $callback);

    /**
     * Enable the query log on the connection.
     *
     * @return void
     */
    public function enableQueryLog();

    /**
     * Log a query in the connection's query log.
     *
     * @param string $query
     * @param array $bindings
     * @param float|null $time
     * @return void
     */
    public function logQuery($query, $bindings, $time = null);
    /**
     * Get the event dispatcher used by the connection.
     *
     * @return Dispatcher
     */
    public function getEventDispatcher();

    /**
     * Set the event dispatcher instance on the connection.
     *
     * @param  Dispatcher  $events
     * @return $this
     */
    public function setEventDispatcher(Dispatcher $dispatcher);

    /**
     * Unset the event dispatcher for this connection.
     *
     * @return void
     */
    public function unsetEventDispatcher();

    /**
     * Determine if the connection is in a "dry run".
     *
     * @return bool
     */
    public function pretending();

    /**
     * Get the connection query log.
     *
     * @return array
     */
    public function getQueryLog();

    /**
     * Clear the query log.
     *
     * @return void
     */
    public function flushQueryLog();
}

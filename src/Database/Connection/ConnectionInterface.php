<?php

namespace Terablaze\Database\Connection;

use Closure;
use PDO;
use Terablaze\Database\Exception\QueryException;
use Terablaze\Database\Query\DatabaseTransactionsManager;
use Terablaze\Database\Query\Expression\ExpressionBuilder;
use Terablaze\Database\Query\QueryBuilderInterface;
use Terablaze\Database\Logging\QueryLogger;
use Terablaze\Database\Schema\SchemaInterface;
use Terablaze\EventDispatcher\Dispatcher;

interface ConnectionInterface
{
    /**
     * Establish a database connection.
     *
     * @return \PDO
     */
    public function connect();

    public function setName(string $name): self;

    public function getName(): string;

    /**
     * Get an option from the configuration options.
     *
     * @param  string|null  $option
     * @return mixed
     */
    public function getConfig($option = null);

    /**
     * Get the PDO driver name.
     *
     * @return string
     */
    public function getDriverName();

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

    /**
     * Executes an optionally parametrized, SQL query.
     *
     * If the query is parametrized, a prepared statement is used.runQueryCallback
     * If an SQLLogger is configured, the execution is logged.
     *
     * @param string $sql SQL query
     * @param array<string, mixed> $params Query parameters
     *
     * @return \PDOStatement|bool The executed statement.
     *
     * @throws QueryException
     */
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

    /**
     * Set the transaction manager instance on the connection.
     *
     * @param  DatabaseTransactionsManager  $manager
     * @return $this
     */
    public function setTransactionManager(DatabaseTransactionsManager $manager);

    /**
     * Unset the transaction manager for this connection.
     *
     * @return void
     */
    public function unsetTransactionManager();

    /**
     * Execute a Closure within a transaction.
     *
     * @param  \Closure  $callback
     * @param  int  $attempts
     * @return mixed
     *
     * @throws \Throwable
     */
    public function transaction(Closure $callback, $attempts = 1);

    /**
     * Start a new database transaction.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function beginTransaction();

    /**
     * Commit the active database transaction.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function commit();

    /**
     * Rollback the active database transaction.
     *
     * @param  int|null  $toLevel
     * @return void
     *
     * @throws \Throwable
     */
    public function rollBack($toLevel = null);

    /**
     * Get the number of active transactions.
     *
     * @return int
     */
    public function transactionLevel();

    /**
     * Execute the callback after a transaction commits.
     *
     * @param  callable  $callback
     * @return void
     *
     * @throws \RuntimeException
     */
    public function afterCommit($callback);

    /**
     * Enable foreign key constraints.
     *
     * @return bool
     */
    public function enableForeignKeyConstraints();


    /**
     * Disable foreign key constraints.
     *
     * @return bool
     */
    public function disableForeignKeyConstraints();
}

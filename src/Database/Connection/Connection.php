<?php

namespace TeraBlaze\Database\Connection;

use Closure;
use PDO;
use PDOException;
use PDOStatement;
use TeraBlaze\Database\Events\QueryExecuted;
use TeraBlaze\Database\Exception\ConnectionLost;
use TeraBlaze\Database\Exception\QueryException;
use TeraBlaze\Database\Query\Expression\ExpressionBuilder;
use TeraBlaze\Database\Query\QueryBuilderInterface;
use TeraBlaze\Database\Logging\QueryLogger;
use TeraBlaze\Database\Schema\SchemaInterface;
use TeraBlaze\EventDispatcher\Dispatcher;
use Throwable;

abstract class Connection implements ConnectionInterface
{
    /**
     * The default PDO connection options.
     *
     * @var array
     */
    protected $options = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
//        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    /** @var QueryLogger $queryLogger */
    protected $queryLogger;

    protected ?PDO $pdo;

    protected array $config;

    /**
     * All of the queries run against the connection.
     *
     * @var array
     */
    protected $queryLog = [];

    /**
     * Indicates whether queries are being logged.
     *
     * @var bool
     */
    protected $loggingQueries = false;

    /**
     * Indicates if the connection is in a "dry run".
     *
     * @var bool
     */
    protected $pretending = false;

    /**
     * The event dispatcher instance.
     *
     * @var Dispatcher
     */
    protected $dispatcher;

    protected $name;

    /** @var int */
    protected int $defaultFetchMode = PDO::FETCH_ASSOC;
    private ExpressionBuilder $expr;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->expr = new ExpressionBuilder($this);
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the ExpressionBuilder for the connection.
     *
     * @return ExpressionBuilder
     */
    public function getExpressionBuilder(): ExpressionBuilder
    {
        return $this->expr;
    }

    /**
     * Executes an, optionally parametrized, SQL query.
     *
     * If the query is parametrized, a prepared statement is used.runQueryCallback
     * If an SQLLogger is configured, the execution is logged.
     *
     * @param string $sql SQL query
     * @param array<string, mixed> $params Query parameters
     *
     * @return PDOStatement|bool The executed statement.
     *
     * @throws QueryException
     */
    public function execute($sql, array $params = [])
    {
        $start = microtime(true);

        $pdo = $this->pdo();

        ['sql' => $sql, 'params' => $params] = $this->fixSqlAndParams($sql, $params);

        $this->getQueryLogger()->startLog($sql, $params);

        try {
            $stmt = $pdo->prepare($sql);
            if ($this->pretending()) {
                $this->logQuery($stmt->queryString, $params, $this->getElapsedTime($start));
                return true;
            }
            $stmt->execute($params);
            $stmt->setFetchMode($this->defaultFetchMode);

            $this->getQueryLogger()->stopLog($stmt->rowCount());
            $this->logQuery($sql, $params, $this->getElapsedTime($start));
            return $stmt;
        } catch (Throwable $e) {
            $this->getQueryLogger()->stopLogForFailed($e);
            $this->handleExceptionDuringQuery(
                $e,
                $sql,
                $params
            );
        }

        return false;
    }

    public function getQueryBuilder(): QueryBuilderInterface
    {
        return $this->query();
    }

    public function quote($value, $type = PDO::PARAM_STR)
    {
        return $this->pdo->quote($value, $type);
    }

    public function escape($value, $type = PDO::PARAM_STR)
    {
        return $this->quote($value, $type);
    }

    public function setQueryLogger(QueryLogger $queryLogger): self
    {
        $this->queryLogger = $queryLogger;

        return $this;
    }

    public function getQueryLogger(): QueryLogger
    {
        return $this->queryLogger = $this->queryLogger ?? new QueryLogger();
    }

    public function getMigrationsTable(): string
    {
        return $this->config['migrations'] ?? getConfig('database.migrations', 'migrations');
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Get the ID of the last row that was inserted
     */
    public function getLastInsertId(): string
    {
        return $this->pdo()->lastInsertId();
    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param  Closure  $callback
     * @return array
     */
    public function pretend(Closure $callback)
    {
        return $this->withFreshQueryLog(function () use ($callback) {
            $this->pretending = true;

            // Basically to make the database connection "pretend", we will just return
            // the default values for all the query methods, then we will return an
            // array of queries that were "executed" within the Closure callback.
            $callback($this);

            $this->pretending = false;

            return $this->queryLog;
        });
    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param  Closure  $callback
     * @return array
     */
    protected function withFreshQueryLog($callback)
    {
        $loggingQueries = $this->loggingQueries;

        // First we will back up the value of the logging queries property and then
        // we'll be ready to run callbacks. This query log will also get cleared
        // so we will have a new log of all the queries that are executed now.
        $this->enableQueryLog();

        $this->queryLog = [];

        // Now we'll execute this callback and capture the result. Once it has been
        // executed we will restore the value of query logging and give back the
        // value of the callback so the original callers can have the results.
        $result = $callback();

        $this->loggingQueries = $loggingQueries;

        return $result;
    }

    /**
     * Enable the query log on the connection.
     *
     * @return void
     */
    public function enableQueryLog()
    {
        $this->loggingQueries = true;
    }
    /**
     * Log a query in the connection's query log.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  float|null  $time
     * @return void
     */
    public function logQuery($query, $bindings, $time = null)
    {
        $this->event(new QueryExecuted($query, $bindings, $time, $this));

        if ($this->loggingQueries) {
            $this->queryLog[] = compact('query', 'bindings', 'time');
        }
    }

    /**
     * Get the event dispatcher used by the connection.
     *
     * @return Dispatcher
     */
    public function getEventDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Set the event dispatcher instance on the connection.
     *
     * @param  Dispatcher  $events
     * @return $this
     */
    public function setEventDispatcher(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        return $this;
    }

    /**
     * Unset the event dispatcher for this connection.
     *
     * @return void
     */
    public function unsetEventDispatcher()
    {
        $this->dispatcher = null;
    }

    /**
     * Determine if the connection is in a "dry run".
     *
     * @return bool
     */
    public function pretending()
    {
        return $this->pretending === true;
    }

    /**
     * Get the connection query log.
     *
     * @return array
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * Clear the query log.
     *
     * @return void
     */
    public function flushQueryLog()
    {
        $this->queryLog = [];
    }

    /**
     * Fire the given event if possible.
     *
     * @param  mixed  $event
     * @return void
     */
    protected function event($event)
    {
        if (isset($this->dispatcher)) {
            $this->dispatcher->dispatch($event);
        }
    }

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param  int  $start
     * @return float
     */
    protected function getElapsedTime($start)
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Closes the connection.
     *
     * @return void
     */
    public function close()
    {
        $this->pdo = null;
    }

    /**
     * @param Throwable $e
     * @param string $sql
     * @param array $params
     */
    public function handleExceptionDuringQuery(Throwable $e, string $sql, array $params = []): void
    {
        $this->throw(
            QueryException::driverExceptionDuringQuery(
                $e,
                $sql,
                $params
            )
        );
    }

    /**
     * @param PDOException $e
     */
    private function throw(PDOException $e): void
    {
        if ($e instanceof ConnectionLost) {
            $this->close();
        }

        throw $e;
    }

    /**
     * @param string $sql
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function fixSqlAndParams(string $sql, array $params): array
    {
        foreach ($params as $key => $values) {
            if (is_bool($values)) {
                $params[$key] = (int)$values;
            }
            if (is_array($values)) {
                // get placeholder from array, e.g. ids => [7,12,3] would be ':ids'
                $oldPlaceholder = ':' . $key;
                $newPlaceholders = '';
                $newParams = [];
                // loop through array to create new placeholders & new named parameters
                for ($i = 0, $count = count($values); $i < $count; $i++) {
                    // this gives us :ids1, :ids2, :ids3 etc
                    $newKey = $oldPlaceholder . ($i + 1);
                    $newPlaceholders .= $newKey . ', ';
                    // this builds an associative array of the new named parameters
                    $newParams[mb_substr($newKey, 1)] = $values[$i];
                }
                //trim off the trailing comma and space
                $newPlaceholders = rtrim($newPlaceholders, ', ');

                // remove the old parameter
                unset($params[$key]);

                // and replace with the new ones
                $params = array_merge($params, $newParams);

                // amend the query
                $sql = str_replace($oldPlaceholder, $newPlaceholders, $sql);
            }
        }
        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }

    public function createTable(string $table): SchemaInterface
    {
        return $this->getSchema($table)->setType('create');
    }

    public function alterTable(string $table): SchemaInterface
    {
        return $this->getSchema($table)->setType('alter');
    }

    public function dropTable(string $table): SchemaInterface
    {
        return $this->getSchema($table)->setType('drop');
    }

    public function dropTableIfExists(string $table): SchemaInterface
    {
        return $this->getSchema($table)->setType('dropIfExists');
    }

    public function renameTable(string $from, string $to): SchemaInterface
    {
        return $this->getSchema($from)->setType('rename')->renameTo($to);
    }

    /**
     * Enable foreign key constraints.
     *
     * @return bool
     */
    public function enableForeignKeyConstraints()
    {
        return $this->execute($this->compileEnableForeignKeyConstraints());
    }

    /**
     * Disable foreign key constraints.
     *
     * @return bool
     */
    public function disableForeignKeyConstraints()
    {
        return $this->execute($this->compileDisableForeignKeyConstraints());
    }

    /**
     * Enable foreign key constraints.
     *
     * @return string
     */
    abstract protected function compileEnableForeignKeyConstraints();


    /**
     * Disable foreign key constraints.
     *
     * @return string
     */
    abstract protected function compileDisableForeignKeyConstraints();
}

<?php

namespace TeraBlaze\Database\Connection;

use PDO;
use PDOException;
use PDOStatement;
use TeraBlaze\Database\Exception\ConnectionLost;
use TeraBlaze\Database\Exception\QueryException;
use TeraBlaze\Database\Query\Expression\ExpressionBuilder;
use TeraBlaze\Database\Query\QueryBuilderInterface;
use TeraBlaze\Database\Logging\QueryLogger;
use Throwable;

abstract class Connection implements ConnectionInterface
{
    protected $dateTimeMode = 'DATETIME';

    /** @var QueryLogger $queryLogger */
    protected $queryLogger;

    protected ?PDO $pdo;

    protected array $config;

    /** @var int */
    protected int $defaultFetchMode = PDO::FETCH_ASSOC;
    private ExpressionBuilder $expr;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->expr = new ExpressionBuilder($this);
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
     * If the query is parametrized, a prepared statement is used.
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
        $pdo = $this->pdo();

        ['sql' => $sql, 'params' => $params] = $this->fixSqlAndParams($sql, $params);

        $this->getQueryLogger()->startLog($sql, $params);

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stmt->setFetchMode($this->defaultFetchMode);

            $this->getQueryLogger()->stopLog($stmt->rowCount());
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

    public function getDateTimeMode(): string
    {
        return $this->dateTimeMode;
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
}

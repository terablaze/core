<?php

namespace TeraBlaze\Ripana\Database\Query;

use TeraBlaze\Ripana\Database\Exception\ArgumentException;
use TeraBlaze\Ripana\Database\Exception\ServiceException;
use TeraBlaze\Ripana\Database\Exception\SqlException;

/**
 * Interface QueryInterface
 * @package TeraBlaze\Ripana\Database
 */
interface QueryInterface
{
    /**
     * Sets whether to dump the built query before executing or not.
     * @param bool|string $dumpSql if $dumpSql is string, attempts to use it as the function to dump
     * @return QueryInterface
     */
    public function dumpSql($dumpSql = true): QueryInterface;

    /**
     * Saves to the database based on current query using passed data
     * @param $data
     * @return mixed
     */
    public function save($data);

    /**
     * Deletes matched row(s) based on current query
     * @return mixed
     */
    public function delete();

    /**
     * The base table for current query
     * @param $table
     * @param array $fields
     * @return $this
     * @throws ArgumentException
     */
    public function table($table, $fields = ["*"]): QueryInterface;

    /**
     * Alias for table()
     * @param $table
     * @param array $fields
     * @return $this
     * @throws ArgumentException
     */
    public function from($table, $fields = ["*"]): QueryInterface;

    /**
     * Constructs a join for the current query
     * @param $join
     * @param $on
     * @param array $fields
     * @return $this
     * @throws ArgumentException
     */
    public function join($join, $on, $fields = []): QueryInterface;

    /**
     * Constructs a left join for the current query
     * @param $join
     * @param $on
     * @param array $fields
     * @return QueryInterface
     */
    public function leftJoin($join, $on, $fields = []): QueryInterface;

    /**
     * Constructs a right join for the current query
     * @param $join
     * @param $on
     * @param array $fields
     * @return QueryInterface
     */
    public function rightJoin($join, $on, $fields = []): QueryInterface;

    /**
     * Constructs a cross join for the current query
     * @param $join
     * @return QueryInterface
     */
    public function crossJoin($join): QueryInterface;

    /**
     * Constructs an order clause for the current query
     * @param $order
     * @return QueryInterface
     */
    public function order($order): QueryInterface;

    /**
     * Constructs a where clause for the current query
     * @return QueryInterface
     */
    public function where(): QueryInterface;

    /**
     * Construct a limit for the current query
     * @param int $limit
     * @param int $page
     * @return QueryInterface
     */
    public function limit(int $limit, int $page = 1): QueryInterface;

    /**
     * Returns the number of rows matched
     * @return int
     */
    public function count(): int;

    /**
     * Returns the first row matched
     * @return mixed
     */
    public function first();

    /**
     * Returns all matched rows
     *
     * @return array<string|int, mixed>
     * @throws SqlException
     * @throws ServiceException
     */
    public function all(): array;
}

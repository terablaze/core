<?php

namespace TeraBlaze\Ripana\Database\QueryBuilder;

use PDOStatement;
use TeraBlaze\Ripana\Database\Connection\ConnectionInterface;
use TeraBlaze\Ripana\Database\Exception\QueryException;
use TeraBlaze\Ripana\Database\QueryBuilder\Expression\ExpressionBuilder;

interface QueryBuilderInterface
{
    /*
     * The query types.
     */
    public const INSERT = 0;
    public const SELECT = 1;
    public const UPDATE = 2;
    public const DELETE = 3;

    /**
     * Gets an ExpressionBuilder used for object-oriented construction of query expressions.
     * This producer method is intended for convenient inline usage. Example:
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u')
     *         ->from('users', 'u')
     *         ->where($qb->expr()->eq('u.id', 1));
     * </code>
     *
     * For more complex expression construction, consider storing the expression
     * builder object in a local variable.
     *
     * @return ExpressionBuilder
     */
    public function expr(): ExpressionBuilder;

    /**
     * Get the database connection instance.
     *
     * @return ConnectionInterface
     */
    public function getConnection();

    /**
     * Executes this query using the bound parameters and their types.
     *
     * @return PDOStatement|int
     *
     * @throws QueryException
     */
    public function execute();

    /**
     * Gets the complete SQL string formed by the current specifications of this QueryBuilder.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u')
     *         ->from('User', 'u')
     *     echo $qb->getSQL(); // SELECT u FROM User u
     * </code>
     *
     * @return string The SQL query string.
     */
    public function getSQL(): string;

    /**
     * Sets a query parameter for the query being constructed.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u')
     *         ->from('users', 'u')
     *         ->where('u.id = :user_id')
     *         ->setParameter(':user_id', 1);
     * </code>
     *
     * @param int|string $key Parameter position or name
     * @param mixed $value Parameter value
     *
     * @return $this This QueryBuilder instance.
     */
    public function setParameter($key, $value): self;

    /**
     * Sets a collection of query parameters for the query being constructed.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u')
     *         ->from('users', 'u')
     *         ->where('u.id = :user_id1 OR u.id = :user_id2')
     *         ->setParameters(array(
     *             ':user_id1' => 1,
     *             ':user_id2' => 2
     *         ));
     * </code>
     *
     * @param array<int|string, mixed> $params Parameters to set
     *
     * @return $this This QueryBuilder instance.
     */
    public function setParameters(array $params): self;

    /**
     * Gets all defined query parameters for the query being constructed indexed by parameter index or name.
     *
     * @return array<int|string, mixed> The currently defined query parameters
     */
    public function getParameters(): array;

    /**
     * Gets a (previously set) query parameter of the query being constructed.
     *
     * @param mixed $key The key (index or name) of the bound parameter.
     *
     * @return mixed The value of the bound parameter.
     */
    public function getParameter($key);

    /**
     * Either appends to or replaces a single, generic query part.
     *
     * The available parts are: 'select', 'from', 'set', 'where',
     * 'groupBy', 'having' and 'orderBy'.
     *
     * @param string $sqlPartName
     * @param mixed $sqlPart
     * @param bool $append
     *
     * @return $this This QueryBuilder instance.
     */
    public function add($sqlPartName, $sqlPart, bool $append = false): self;

    /**
     * Specifies an item that is to be returned in the query result.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u.id', 'p.id')
     *         ->from('users', 'u')
     *         ->leftJoin('u', 'phonenumbers', 'p', 'u.id = p.user_id');
     * </code>
     *
     * @param string|string[] $column
     *
     * @return $this This QueryBuilder instance.
     */
    public function select($column = null): self;

    /**
     * Adds DISTINCT to the query.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u.id')
     *         ->distinct()
     *         ->from('users', 'u')
     * </code>
     *
     * @return $this This QueryBuilder instance.
     */
    public function distinct(): self;

    /**
     * Adds an item that is to be returned in the query result.
     * Appends to any previously specified selections, if any.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u.id')
     *         ->addSelect('p.id')
     *         ->from('users', 'u')
     *         ->leftJoin('u', 'phonenumbers', 'u.id = p.user_id');
     * </code>
     *
     * @param string|string[]|null $column Array or list of columns to select. Leave empty for wildcard select.
     *
     * @return $this This QueryBuilder instance.
     */
    public function addSelect($column = null): self;

    /**
     * Turns the query being built into a bulk delete query that ranges over
     * a certain table.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->delete('users', 'u')
     *         ->where('u.id = :user_id')
     *         ->setParameter(':user_id', 1);
     * </code>
     *
     * @param string $delete The table whose rows are subject to the deletion.
     * @param string $alias The table alias used in the constructed query.
     *
     * @return $this This QueryBuilder instance.
     */
    public function delete($delete = null, $alias = null): self;

    /**
     * Turns the query being built into a bulk update query that ranges over
     * a certain table
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->update('counters', 'c')
     *         ->set('c.value', 'c.value + 1')
     *         ->where('c.id = ?');
     * </code>
     *
     * @param string $update The table whose rows are subject to the update.
     * @param string $alias The table alias used in the constructed query.
     *
     * @return $this This QueryBuilder instance.
     */
    public function update($update = null, $alias = null): self;

    /**
     * Turns the query being built into an insert query that inserts into
     * a certain table
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->insert('users')
     *         ->values(
     *             array(
     *                 'name' => '?',
     *                 'password' => '?'
     *             )
     *         );
     * </code>
     *
     * @param string $insert The table into which the rows should be inserted.
     *
     * @return $this This QueryBuilder instance.
     */
    public function insert($insert = null): self;

    public function save($values, $parameters = []): self;

    /**
     * Creates and adds a query root corresponding to the table identified by the
     * given alias, forming a cartesian product with any existing query roots.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u.id')
     *         ->from('users', 'u')
     * </code>
     *
     * @param string $table The table.
     * @param string|null $alias The alias of the table.
     *
     * @return $this This QueryBuilder instance.
     */
    public function from(string $table, ?string $alias = null): self;

    /**
     * Creates and adds a query root corresponding to the table identified by the
     * given alias, forming a cartesian product with any existing query roots.
     *
     * Alias for from()
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u.id')
     *         ->from('users', 'u')
     * </code>
     *
     * @param string $table The table.
     * @param string|null $alias The alias of the table.
     *
     * @return $this This QueryBuilder instance.
     */
    public function table(string $table, ?string $alias = null): self;

    /**
     * Creates and adds a join to the query.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->join('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause.
     * @param string $join The table name to join.
     * @param string $alias The alias of the join table.
     * @param string $condition The condition for the join.
     *
     * @return $this This QueryBuilder instance.
     */
    public function join($fromAlias, $join, $alias, $condition = null): self;

    /**
     * Creates and adds a join to the query.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->innerJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause.
     * @param string $join The table name to join.
     * @param string $alias The alias of the join table.
     * @param string $condition The condition for the join.
     *
     * @return $this This QueryBuilder instance.
     */
    public function innerJoin($fromAlias, $join, $alias, $condition = null): self;

    /**
     * Creates and adds a left join to the query.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->leftJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause.
     * @param string $join The table name to join.
     * @param string $alias The alias of the join table.
     * @param string $condition The condition for the join.
     *
     * @return $this This QueryBuilder instance.
     */
    public function leftJoin($fromAlias, $join, $alias, $condition = null): self;

    /**
     * Creates and adds a right join to the query.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->rightJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause.
     * @param string $join The table name to join.
     * @param string $alias The alias of the join table.
     * @param string $condition The condition for the join.
     *
     * @return $this This QueryBuilder instance.
     */
    public function rightJoin($fromAlias, $join, $alias, $condition = null): self;

    /**
     * Sets a new value for a column in a bulk update query.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->update('counters', 'c')
     *         ->set('c.value', 'c.value + 1')
     *         ->where('c.id = ?');
     * </code>
     *
     * @param string $key The column to set.
     * @param string $value The value, expression, placeholder, etc.
     *
     * @return $this This QueryBuilder instance.
     */
    public function set($key, $value): self;

    /**
     * Specifies one or more restrictions to the query result.
     * Replaces any previously specified restrictions, if any.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('c.value')
     *         ->from('counters', 'c')
     *         ->where('c.id = ?');
     *
     *     // You can optionally programatically build and/or expressions
     *     $qb = $connection->getQueryBuilder();
     *
     *     $or = $qb->expr()->orx();
     *     $or->add($qb->expr()->eq('c.id', 1));
     *     $or->add($qb->expr()->eq('c.id', 2));
     *
     *     $qb->update('counters', 'c')
     *         ->set('c.value', 'c.value + 1')
     *         ->where($or);
     * </code>
     *
     * @param mixed $predicates The restriction predicates.
     *
     * @return $this This QueryBuilder instance.
     */
    public function where($predicates): self;

    /**
     * Adds one or more restrictions to the query results, forming a logical
     * conjunction with any previously specified restrictions.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u')
     *         ->from('users', 'u')
     *         ->where('u.username LIKE ?')
     *         ->andWhere('u.is_active = 1');
     * </code>
     *
     * @param mixed $where The query restrictions.
     *
     * @return $this This QueryBuilder instance.
     * @see where()
     *
     */
    public function andWhere($where): self;

    /**
     * Adds one or more restrictions to the query results, forming a logical
     * disjunction with any previously specified restrictions.
     *
     * <code>
     *     $qb = $em->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->where('u.id = 1')
     *         ->orWhere('u.id = 2');
     * </code>
     *
     * @param mixed $where The WHERE statement.
     *
     * @return $this This QueryBuilder instance.
     * @see where()
     *
     */
    public function orWhere($where): self;

    /**
     * Specifies a grouping over the results of the query.
     * Replaces any previously specified groupings, if any.
     *
     * USING AN ARRAY ARGUMENT IS DEPRECATED. Pass each value as an individual argument.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->groupBy('u.id');
     * </code>
     *
     * @param string|string[] $groupBy The grouping expression. USING AN ARRAY IS DEPRECATED.
     *                                 Pass each value as an individual argument.
     *
     * @return $this This QueryBuilder instance.
     */
    public function groupBy($groupBy): self;

    /**
     * Adds a grouping expression to the query.
     *
     * USING AN ARRAY ARGUMENT IS DEPRECATED. Pass each value as an individual argument.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->groupBy('u.lastLogin')
     *         ->addGroupBy('u.createdAt');
     * </code>
     *
     * @param string|string[] $groupBy The grouping expression. USING AN ARRAY IS DEPRECATED.
     *                                 Pass each value as an individual argument.
     *
     * @return $this This QueryBuilder instance.
     */
    public function addGroupBy($groupBy): self;

    /**
     * Adds limit to the query
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u')
     *         ->from('users')
     *         ->limit(10, 11);
     * </code>
     *
     * @param int $limit
     * @param int $offset
     * @return $this
     */
    public function limit(int $limit, int $offset = 0): self;

    /**
     * Adds limit to the query and offset by page number
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->select('u')
     *         ->from('users')
     *         ->limitByPage(10, 4);
     * </code>
     *
     * @param int $limit
     * @param int $page
     * @return $this
     */
    public function limitByPage(int $limit, int $page = 1): self;

    /**
     * Sets a value for a column in an insert query.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->insert('users')
     *         ->values(
     *             array(
     *                 'name' => '?'
     *             )
     *         )
     *         ->setValue('password', '?');
     * </code>
     *
     * @param string $column The column into which the value should be inserted.
     * @param string $value The value that should be inserted into the column.
     *
     * @return $this This QueryBuilder instance.
     */
    public function setValue($column, $value): self;

    /**
     * Specifies values for an insert query indexed by column names.
     * Replaces any previous values, if any.
     *
     * <code>
     *     $qb = $connection->getQueryBuilder()
     *         ->insert('users')
     *         ->values(
     *             array(
     *                 'name' => '?',
     *                 'password' => '?'
     *             )
     *         );
     * </code>
     *
     * @param mixed[] $values The values to specify for the insert query indexed by column names.
     *
     * @return $this This QueryBuilder instance.
     */
    public function values(array $values): self;

    /**
     * Specifies a restriction over the groups of the query.
     * Replaces any previous having restrictions, if any.
     *
     * @param mixed $having The restriction over the groups.
     *
     * @return $this This QueryBuilder instance.
     */
    public function having($having): self;

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * conjunction with any existing having restrictions.
     *
     * @param mixed $having The restriction to append.
     *
     * @return $this This QueryBuilder instance.
     */
    public function andHaving($having): self;

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * disjunction with any existing having restrictions.
     *
     * @param mixed $having The restriction to add.
     *
     * @return $this This QueryBuilder instance.
     */
    public function orHaving($having): self;

    /**
     * Specifies an ordering for the query results.
     * Replaces any previously specified orderings, if any.
     *
     * @param string $sort The ordering expression.
     * @param string $order The ordering direction.
     *
     * @return $this This QueryBuilder instance.
     */
    public function orderBy($sort, $order = null): self;

    /**
     * Adds an ordering to the query results.
     *
     * @param string $sort The ordering expression.
     * @param string $order The ordering direction.
     *
     * @return $this This QueryBuilder instance.
     */
    public function addOrderBy($sort, $order = null): self;

    /**
     * Gets a query part by its name.
     *
     * @param string $queryPartName
     *
     * @return mixed
     */
    public function getQueryPart($queryPartName);

    /**
     * Gets all query parts.
     *
     * @return mixed[]
     */
    public function getQueryParts();

    /**
     * Resets SQL parts.
     *
     * @param string[]|null $queryPartNames
     *
     * @return $this This QueryBuilder instance.
     */
    public function resetQueryParts($queryPartNames = null): self;

    /**
     * Resets a single SQL part.
     *
     * @param string $queryPartName
     *
     * @return $this This QueryBuilder instance.
     */
    public function resetQueryPart($queryPartName): self;

    /**
     * @return array<string|int, mixed>
     */
    public function all(): array;

    /**
     * @return array<string|int, mixed>|null
     */
    public function first(): ?array;

    /**
     * @return int
     */
    public function count(): int;

    public function getLastInsertId(): string;

    public function getType(): int;
}
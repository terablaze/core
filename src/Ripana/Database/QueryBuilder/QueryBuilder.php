<?php

namespace TeraBlaze\Ripana\Database\QueryBuilder;

use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Ripana\Database\Connection\ConnectionInterface;
use TeraBlaze\Ripana\Database\Exception\QueryException;
use TeraBlaze\Ripana\Database\QueryBuilder\Expression\CompositeExpression;
use PDOStatement;
use TeraBlaze\Ripana\Database\QueryBuilder\Expression\ExpressionBuilder;

abstract class QueryBuilder implements QueryBuilderInterface
{
    /*
     * The default values of SQL parts collection
     */
    protected const SQL_PARTS_DEFAULTS = [
        'select' => [],
        'distinct' => false,
        'from' => [],
        'join' => [],
        'set' => [],
        'where' => null,
        'groupBy' => [],
        'having' => null,
        'orderBy' => [],
        'values' => [],
    ];

    protected ConnectionInterface $connection;

    protected $saveCall = null;

    /**
     * The array of SQL parts collected.
     *
     * @var mixed[]
     */
    protected $sqlParts = self::SQL_PARTS_DEFAULTS;

    /**
     * The complete SQL string for this query.
     *
     * @var string
     */
    protected $sql;

    /**
     * The query parameters.
     *
     * @var array<int, mixed>|array<string, mixed>
     */
    protected $params = [];


    /**
     * The parameter type map of this query.
     *
     * @var array<int, int|string|null>|array<string, int|string|null>
     */
    protected $paramTypes = [];

    /**
     * The type of query this is. Can be insert, select, update or delete.
     *
     * @var int
     */
    protected int $type;

    /**
     * The maximum number of results to retrieve or NULL to retrieve all results.
     *
     * @var int|null
     */
    protected ?int $limit = null;

    /**
     * The index of the first result to retrieve.
     *
     * @var int
     */
    protected int $offset;

    /**
     * QueryBuilder constructor.
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

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
    public function expr(): ExpressionBuilder
    {
        return $this->connection->getExpressionBuilder();
    }

    /**
     * Get the database connection instance.
     *
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }


    /**
     * Executes this query using the bound parameters and their types.
     *
     * @return PDOStatement|int
     *
     * @throws QueryException
     */
    public function execute()
    {
        return $this->connection->execute($this->getSQL(), $this->params);
    }

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
    public function getSQL(): string
    {
        if (!is_null($this->saveCall)) {
            $isInsert = empty($this->sqlParts['where']);

            $table = $this->sqlParts['from'][0]['table'];
            $this->resetQueryPart('from');

            if ($isInsert) {
                $this->insert($table);
                $this->values($this->saveCall['values']);
            } else {
                $this->update($table);
                foreach ($this->saveCall['values'] as $key => $value) {
                    $this->set($key, $value);
                }
            }
            $this->setParameters($this->saveCall['parameters']);

            $this->saveCall = null;
            return $this->getSQL();
        }

        if (!isset($this->type)) {
            $this->type = self::SELECT;
        }

        switch ($this->type) {
            case self::INSERT:
                $sql = $this->getSQLForInsert();
                break;

            case self::DELETE:
                $sql = $this->getSQLForDelete();
                break;

            case self::UPDATE:
                $sql = $this->getSQLForUpdate();
                break;

            case self::SELECT:
            default:
                $sql = $this->getSQLForSelect();
                break;
        }

        $this->sql = $sql;

        return $sql;
    }

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
    public function setParameter($key, $value): self
    {
        $this->params[$key] = $value;

        return $this;
    }

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
    public function setParameters(array $params): self
    {
        $this->params = $params + $this->params;

        return $this;
    }

    /**
     * Gets all defined query parameters for the query being constructed indexed by parameter index or name.
     *
     * @return array<int|string, mixed> The currently defined query parameters
     */
    public function getParameters(): array
    {
        return $this->params;
    }

    /**
     * Gets a (previously set) query parameter of the query being constructed.
     *
     * @param mixed $key The key (index or name) of the bound parameter.
     *
     * @return mixed The value of the bound parameter.
     */
    public function getParameter($key)
    {
        return $this->params[$key] ?? null;
    }

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
    public function add($sqlPartName, $sqlPart, bool $append = false): self
    {
        $isArray = is_array($sqlPart);
        $isMultiple = is_array($this->sqlParts[$sqlPartName]);

        if ($isMultiple && !$isArray) {
            $sqlPart = [$sqlPart];
        }

        if ($append) {
            if (in_array($sqlPartName, ['orderBy', 'groupBy', 'select', 'set'])) {
                foreach ($sqlPart as $part) {
                    $this->sqlParts[$sqlPartName][] = $part;
                }
            } elseif ($isArray && is_array($sqlPart[key($sqlPart)])) {
                $key = key($sqlPart);
                $this->sqlParts[$sqlPartName][$key][] = $sqlPart[$key];
            } elseif ($isMultiple) {
                $this->sqlParts[$sqlPartName][] = $sqlPart;
            } else {
                $this->sqlParts[$sqlPartName] = $sqlPart;
            }

            return $this;
        }

        $this->sqlParts[$sqlPartName] = $sqlPart;

        return $this;
    }

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
    public function select($column = null/*, string ...$columns*/): self
    {
        $this->type = self::SELECT;

        $columns = is_array($column) ? $column : func_get_args();

        return $this->add('select', $columns);
    }

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
    public function distinct(): self
    {
        $this->sqlParts['distinct'] = true;

        return $this;
    }

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
    public function addSelect($column = null/*, string ...$columns*/): self
    {
        $this->type = self::SELECT;

        $columns = is_array($column) ? $column : func_get_args();

        return $this->add('select', $columns, true);
    }

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
     * @param string $alias  The table alias used in the constructed query.
     *
     * @return $this This QueryBuilder instance.
     */
    public function delete($delete = null, $alias = null): self
    {
        $this->type = self::DELETE;

        if (! $delete) {
            return $this;
        }

        return $this->add('from', [
            'table' => $delete,
            'alias' => $alias,
        ]);
    }

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
     * @param string $alias  The table alias used in the constructed query.
     *
     * @return $this This QueryBuilder instance.
     */
    public function update($update = null, $alias = null): self
    {
        $this->type = self::UPDATE;

        if (! $update) {
            return $this;
        }

        return $this->add('from', [
            'table' => $update,
            'alias' => $alias,
        ]);
    }

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
    public function insert($insert = null): self
    {
        $this->type = self::INSERT;

        if (! $insert) {
            return $this;
        }

        return $this->add('from', ['table' => $insert]);
    }

    public function save($values, $parameters = []): self
    {
        $this->saveCall = [
            'values' => $values,
            'parameters' => $parameters
        ];
        return $this;
    }

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
    public function from(string $table, ?string $alias = null): self
    {
        return $this->add('from', [
            'table' => $table,
            'alias' => $alias,
        ], true);
    }

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
    public function table(string $table, ?string $alias = null): self
    {
        return $this->from($table, $alias);
    }

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
     * @param string $join      The table name to join.
     * @param string $alias     The alias of the join table.
     * @param string $condition The condition for the join.
     *
     * @return $this This QueryBuilder instance.
     */
    public function join($fromAlias, $join, $alias, $condition = null): self
    {
        return $this->innerJoin($fromAlias, $join, $alias, $condition);
    }

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
     * @param string $join      The table name to join.
     * @param string $alias     The alias of the join table.
     * @param string $condition The condition for the join.
     *
     * @return $this This QueryBuilder instance.
     */
    public function innerJoin($fromAlias, $join, $alias, $condition = null): self
    {
        return $this->add('join', [
            $fromAlias => [
                'joinType'      => 'inner',
                'joinTable'     => $join,
                'joinAlias'     => $alias,
                'joinCondition' => $condition,
            ],
        ], true);
    }

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
     * @param string $join      The table name to join.
     * @param string $alias     The alias of the join table.
     * @param string $condition The condition for the join.
     *
     * @return $this This QueryBuilder instance.
     */
    public function leftJoin($fromAlias, $join, $alias, $condition = null): self
    {
        return $this->add('join', [
            $fromAlias => [
                'joinType'      => 'left',
                'joinTable'     => $join,
                'joinAlias'     => $alias,
                'joinCondition' => $condition,
            ],
        ], true);
    }

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
     * @param string $join      The table name to join.
     * @param string $alias     The alias of the join table.
     * @param string $condition The condition for the join.
     *
     * @return $this This QueryBuilder instance.
     */
    public function rightJoin($fromAlias, $join, $alias, $condition = null): self
    {
        return $this->add('join', [
            $fromAlias => [
                'joinType'      => 'right',
                'joinTable'     => $join,
                'joinAlias'     => $alias,
                'joinCondition' => $condition,
            ],
        ], true);
    }

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
     * @param string $key   The column to set.
     * @param string $value The value, expression, placeholder, etc.
     *
     * @return $this This QueryBuilder instance.
     */
    public function set($key, $value): self
    {
        return $this->add('set', $key . ' = ' . $value, true);
    }

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
    public function where($predicates): self
    {
        if (! (func_num_args() === 1 && $predicates instanceof CompositeExpression)) {
            $predicates = CompositeExpression::and(...func_get_args());
        }

        return $this->add('where', $predicates);
    }

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
     * @see where()
     *
     * @param mixed $where The query restrictions.
     *
     * @return $this This QueryBuilder instance.
     */
    public function andWhere($where): self
    {
        $args  = func_get_args();
        $args  = array_filter($args); // https://github.com/doctrine/dbal/issues/4282
        $where = $this->getQueryPart('where');

        if ($where instanceof CompositeExpression && $where->getType() === CompositeExpression::TYPE_AND) {
            if (count($args) > 0) {
                $where = $where->with(...$args);
            }
        } else {
            array_unshift($args, $where);
            $where = CompositeExpression::and(...$args);
        }

        return $this->add('where', $where, true);
    }

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
     * @see where()
     *
     * @param mixed $where The WHERE statement.
     *
     * @return $this This QueryBuilder instance.
     */
    public function orWhere($where): self
    {
        $args  = func_get_args();
        $args  = array_filter($args); // https://github.com/doctrine/dbal/issues/4282
        $where = $this->getQueryPart('where');

        if ($where instanceof CompositeExpression && $where->getType() === CompositeExpression::TYPE_OR) {
            if (count($args) > 0) {
                $where = $where->with(...$args);
            }
        } else {
            array_unshift($args, $where);
            $where = CompositeExpression::or(...$args);
        }

        return $this->add('where', $where, true);
    }

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
    public function groupBy($groupBy/*, string ...$groupBys*/): self
    {
        if (empty($groupBy)) {
            return $this;
        }

        $groupBy = is_array($groupBy) ? $groupBy : func_get_args();

        return $this->add('groupBy', $groupBy, false);
    }

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
    public function addGroupBy($groupBy/*, string ...$groupBys*/): self
    {
        if (empty($groupBy)) {
            return $this;
        }

        $groupBy = is_array($groupBy) ? $groupBy : func_get_args();

        return $this->add('groupBy', $groupBy, true);
    }

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
    public function limit(int $limit, int $offset = 0): self
    {
        $this->limit = $limit;
        $this->offset = $offset;

        return $this;
    }

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
    public function limitByPage(int $limit, int $page = 1): self
    {
        if (empty($page) || $page < 1) {
            $page = 1;
        }

        $this->limit = $limit;
        $this->offset = $limit * ($page - 1);

        return $this;
    }

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
     * @param string $value  The value that should be inserted into the column.
     *
     * @return $this This QueryBuilder instance.
     */
    public function setValue($column, $value): self
    {
        $this->sqlParts['values'][$column] = $value;

        return $this;
    }

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
    public function values(array $values): self
    {
        return $this->add('values', $values);
    }

    /**
     * Specifies a restriction over the groups of the query.
     * Replaces any previous having restrictions, if any.
     *
     * @param mixed $having The restriction over the groups.
     *
     * @return $this This QueryBuilder instance.
     */
    public function having($having): self
    {
        if (! (func_num_args() === 1 && $having instanceof CompositeExpression)) {
            $having = CompositeExpression::and(...func_get_args());
        }

        return $this->add('having', $having);
    }

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * conjunction with any existing having restrictions.
     *
     * @param mixed $having The restriction to append.
     *
     * @return $this This QueryBuilder instance.
     */
    public function andHaving($having): self
    {
        $args   = func_get_args();
        $args   = array_filter($args); // https://github.com/doctrine/dbal/issues/4282
        $having = $this->getQueryPart('having');

        if ($having instanceof CompositeExpression && $having->getType() === CompositeExpression::TYPE_AND) {
            $having = $having->with(...$args);
        } else {
            array_unshift($args, $having);
            $having = CompositeExpression::and(...$args);
        }

        return $this->add('having', $having);
    }

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * disjunction with any existing having restrictions.
     *
     * @param mixed $having The restriction to add.
     *
     * @return $this This QueryBuilder instance.
     */
    public function orHaving($having): self
    {
        $args   = func_get_args();
        $args   = array_filter($args); // https://github.com/doctrine/dbal/issues/4282
        $having = $this->getQueryPart('having');

        if ($having instanceof CompositeExpression && $having->getType() === CompositeExpression::TYPE_OR) {
            $having = $having->with(...$args);
        } else {
            array_unshift($args, $having);
            $having = CompositeExpression::or(...$args);
        }

        return $this->add('having', $having);
    }

    /**
     * Specifies an ordering for the query results.
     * Replaces any previously specified orderings, if any.
     *
     * @param string $sort  The ordering expression.
     * @param string $order The ordering direction.
     *
     * @return $this This QueryBuilder instance.
     */
    public function orderBy($sort, $order = null): self
    {
        return $this->add('orderBy', $sort . ' ' . (! $order ? 'ASC' : $order), false);
    }

    /**
     * Adds an ordering to the query results.
     *
     * @param string $sort  The ordering expression.
     * @param string $order The ordering direction.
     *
     * @return $this This QueryBuilder instance.
     */
    public function addOrderBy($sort, $order = null): self
    {
        return $this->add('orderBy', $sort . ' ' . (! $order ? 'ASC' : $order), true);
    }

    /**
     * Gets a query part by its name.
     *
     * @param string $queryPartName
     *
     * @return mixed
     */
    public function getQueryPart($queryPartName)
    {
        return $this->sqlParts[$queryPartName];
    }

    /**
     * Gets all query parts.
     *
     * @return mixed[]
     */
    public function getQueryParts()
    {
        return $this->sqlParts;
    }

    /**
     * Resets SQL parts.
     *
     * @param string[]|null $queryPartNames
     *
     * @return $this This QueryBuilder instance.
     */
    public function resetQueryParts($queryPartNames = null): self
    {
        if ($queryPartNames === null) {
            $queryPartNames = array_keys($this->sqlParts);
        }

        foreach ($queryPartNames as $queryPartName) {
            $this->resetQueryPart($queryPartName);
        }

        return $this;
    }

    /**
     * Resets a single SQL part.
     *
     * @param string $queryPartName
     *
     * @return $this This QueryBuilder instance.
     */
    public function resetQueryPart($queryPartName): self
    {
        $this->sqlParts[$queryPartName] = self::SQL_PARTS_DEFAULTS[$queryPartName];

        return $this;
    }

    /**
     * @return string
     *
     * @throws QueryException
     */
    protected function getSQLForSelect(): string
    {
        $query = 'SELECT ' . ($this->sqlParts['distinct'] ? 'DISTINCT ' : '') .
            implode(', ', $this->sqlParts['select']);

        $query .= ($this->sqlParts['from'] ? ' FROM ' . implode(', ', $this->getFromClauses()) : '')
            . ($this->sqlParts['where'] !== null ? ' WHERE ' . ((string) $this->sqlParts['where']) : '')
            . ($this->sqlParts['groupBy'] ? ' GROUP BY ' . implode(', ', $this->sqlParts['groupBy']) : '')
            . ($this->sqlParts['having'] !== null ? ' HAVING ' . ((string) $this->sqlParts['having']) : '')
            . ($this->sqlParts['orderBy'] ? ' ORDER BY ' . implode(', ', $this->sqlParts['orderBy']) : '');

        return $this->compileLimit($query);
    }

    /**
     * @return string[]
     */
    protected function getFromClauses(): array
    {
        $fromClauses  = [];
        $knownAliases = [];

        // Loop through all FROM clauses
        foreach ($this->sqlParts['from'] as $from) {
            if ($from['alias'] === null) {
                $tableSql       = $from['table'];
                $tableReference = $from['table'];
            } else {
                $tableSql       = $from['table'] . ' ' . $from['alias'];
                $tableReference = $from['alias'];
            }

            $knownAliases[$tableReference] = true;

            $fromClauses[$tableReference] = $tableSql . $this->getSQLForJoins($tableReference, $knownAliases);
        }

        $this->verifyAllAliasesAreKnown($knownAliases);

        return $fromClauses;
    }

    /**
     * @param array<string,true> $knownAliases
     *
     * @throws QueryException
     */
    protected function verifyAllAliasesAreKnown(array $knownAliases): void
    {
        foreach ($this->sqlParts['join'] as $fromAlias => $joins) {
            if (! isset($knownAliases[$fromAlias])) {
                throw QueryException::unknownAlias($fromAlias, array_keys($knownAliases));
            }
        }
    }

    /**
     * @return bool
     */
    protected function isLimitQuery(): bool
    {
        return $this->limit !== null;
    }

    /**
     * Converts this instance into an INSERT string in SQL.
     *
     * @return string
     */
    protected function getSQLForInsert(): string
    {
        return 'INSERT INTO ' . $this->sqlParts['from']['table'] .
            ' (' . implode(', ', array_keys($this->sqlParts['values'])) . ')' .
            ' VALUES(' . implode(', ', $this->sqlParts['values']) . ')';
    }

    /**
     * Converts this instance into an UPDATE string in SQL.
     *
     * @return string
     */
    protected function getSQLForUpdate(): string
    {
        $table = $this->sqlParts['from']['table']
            . ($this->sqlParts['from']['alias'] ? ' ' . $this->sqlParts['from']['alias'] : '');

        $query = 'UPDATE ' . $table
            . ' SET ' . implode(', ', $this->sqlParts['set'])
            . ($this->sqlParts['where'] !== null ? ' WHERE ' . ((string) $this->sqlParts['where']) : '');

        $query = $this->compileLimit($query);

        return $query;
    }

    /**
     * Converts this instance into a DELETE string in SQL.
     *
     * @return string
     */
    protected function getSQLForDelete(): string
    {
        $table = $this->sqlParts['from']['table']
            . ($this->sqlParts['from']['alias'] ? ' ' . $this->sqlParts['from']['alias'] : '');

        $query = 'DELETE FROM ' . $table
            . ($this->sqlParts['where'] !== null ? ' WHERE ' . ((string) $this->sqlParts['where']) : '');

        return $this->compileLimit($query);
    }

    /**
     * @param string             $fromAlias
     * @param array<string,true> $knownAliases
     *
     * @return string
     *
     * @throws QueryException
     */
    protected function getSQLForJoins($fromAlias, array &$knownAliases): string
    {
        $sql = '';

        if (isset($this->sqlParts['join'][$fromAlias])) {
            foreach ($this->sqlParts['join'][$fromAlias] as $join) {
                if (array_key_exists($join['joinAlias'], $knownAliases)) {
                    throw QueryException::nonUniqueAlias($join['joinAlias'], array_keys($knownAliases));
                }

                $sql .= ' ' . strtoupper($join['joinType'])
                    . ' JOIN ' . $join['joinTable'] . ' ' . $join['joinAlias'];
                if ($join['joinCondition'] !== null) {
                    $sql .= ' ON ' . $join['joinCondition'];
                }

                $knownAliases[$join['joinAlias']] = true;
            }

            foreach ($this->sqlParts['join'][$fromAlias] as $join) {
                $sql .= $this->getSQLForJoins($join['joinAlias'], $knownAliases);
            }
        }

        return $sql;
    }

    /**
     * @return array<string|int, mixed>
     */
    public function all(): array
    {
        if (!isset($this->type)) {
            $this->select('*');
        }

        return $this->execute()->fetchAll();
    }

    /**
     * @return array<string|int, mixed>|null
     */
    public function first(): ?array
    {
        if (!isset($this->type)) {
            $this->select('*');
        }

        $result = $this->limit(1)->execute()->fetchAll();

        if (count($result) === 1) {
            return ArrayMethods::first($result);
        }

        return null;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        $this->select('COUNT(*)');

        return (int) $this->execute()->fetchColumn();
    }

    /**
     * Get the ID of the last row that was inserted
     */
    public function getLastInsertId(): string
    {
        return $this->connection->getLastInsertId();
    }

    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Add limit and offset clauses to the query
     */
    protected function compileLimit(string $query): string
    {
        if (isset($this->limit)) {
            $query .= " LIMIT {$this->limit}";
        }

        if (isset($this->offset)) {
            $query .= " OFFSET {$this->offset}";
        }

        return $query;
    }

    /**
     * Gets a string representation of this QueryBuilder which corresponds to
     * the final SQL query being constructed.
     *
     * @return string The string representation of this QueryBuilder.
     */
    public function __toString()
    {
        return $this->getSQL();
    }

    /**
     * Deep clone of all expression objects in the SQL parts.
     *
     * @return void
     */
    public function __clone()
    {
        foreach ($this->sqlParts as $part => $elements) {
            if (is_array($this->sqlParts[$part])) {
                foreach ($this->sqlParts[$part] as $idx => $element) {
                    if (! is_object($element)) {
                        continue;
                    }

                    $this->sqlParts[$part][$idx] = clone $element;
                }
            } elseif (is_object($elements)) {
                $this->sqlParts[$part] = clone $elements;
            }
        }

        foreach ($this->params as $name => $param) {
            if (! is_object($param)) {
                continue;
            }

            $this->params[$name] = clone $param;
        }
    }
}

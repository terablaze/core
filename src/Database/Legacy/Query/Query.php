<?php

namespace TeraBlaze\Database\Legacy\Query;

use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Database\Legacy\Connectors\ConnectorInterface;
use TeraBlaze\Database\Exception;
use TeraBlaze\Database\Exception\ServiceException;
use TeraBlaze\Database\Exception\SqlException;

abstract class Query implements QueryInterface
{
    /**
     * @var ConnectorInterface $connector
     */
    protected ConnectorInterface $connector;

    protected string $table;

    /**
     * @var string[]
     */
    protected array $fields;

    protected int $limit = 0;

    protected ?int $offset = null;

    /**
     * @var string[]|string
     */
    protected $order;

    /**
     * @var string[]
     */
    protected array $join = [];

    /**
     * @var string[]
     */
    protected array $where = [];

    /**
     * @var bool|string $dumpSql
     */
    protected $dumpSql = false;

    public function __construct(ConnectorInterface $connector)
    {
        $this->connector = $connector;
    }

    /**
     * Returns all matched rows
     *
     * @return array<string|int, mixed>
     * @throws SqlException
     * @throws ServiceException
     */
    abstract public function all(): array;

    public function dumpSql($dumpSql = true): QueryInterface
    {
        $this->dumpSql = $dumpSql;
        return $this;
    }

    public function save($data)
    {
        $isInsert = sizeof($this->where) == 0;

        if ($isInsert) {
            $sql = $this->_buildInsert($data);
        } else {
            $sql = $this->_buildUpdate($data);
        }

        $result = $this->connector->execute($sql, $this->dumpSql);

        if ($result === false) {
            $error = $this->connector->getLastError();
            throw new Exception\SqlException("An error occured while executing your query: {$error} \n" . $sql);
        }

        if ($isInsert) {
            return $this->connector->getLastInsertId();
        }

        return true;
    }

    protected function _buildInsert($data)
    {
        $fields = [];
        $values = [];
        $template = "INSERT INTO `%s` (`%s`) VALUES (%s)";

        foreach ($data as $field => $value) {
            $fields[] = $field;
            $values[] = $this->_quote($value);
        }

        $fields = join("`, `", $fields);
        $values = join(", ", $values);

        return sprintf($template, $this->table, $fields, $values);
    }

    protected function _quote($value)
    {
        if (is_string($value)) {
            $escaped = $this->connector->escape($value);
            return "'{$escaped}'";
        }

        if (is_array($value)) {
            $buffer = [];

            foreach ($value as $i) {
                array_push($buffer, $this->_quote($i));
            }

            $buffer = join(", ", $buffer);
            return "({$buffer})";
        }

        if (is_null($value)) {
            return "NULL";
        }

        if (is_bool($value)) {
            return (int)$value;
        }

        return $this->connector->escape($value);
    }

    protected function _buildUpdate($data)
    {
        $parts = [];
        $where = $limit = "";
        $template = "UPDATE %s SET %s %s %s";

        foreach ($data as $field => $value) {
            $parts[] = "{$field} = " . $this->_quote($value);
        }

        $parts = join(", ", $parts);

        $_where = $this->where;
        if (!empty($_where)) {
            $joined = join(" AND ", $_where);
            $where = "WHERE {$joined}";
        }

        $_limit = $this->limit;
        if (!empty($_limit)) {
            $_offset = $this->offset;
            $limit = "LIMIT {$_offset}, {$_limit}";
        }

        return sprintf($template, $this->table, $parts, $where, $limit);
    }

    public function delete()
    {
        $sql = $this->_buildDelete();
        $result = $this->connector->execute($sql, $this->dumpSql);

        if ($result === false) {
            throw new Exception\SqlException();
        }

        return $this->connector->getAffectedRows();
    }

    protected function _buildDelete()
    {
        $where = $limit = "";
        $template = "DELETE FROM %s %s %s";

        $_where = $this->where;
        if (!empty($_where)) {
            $joined = join(" AND ", $_where);
            $where = "WHERE {$joined}";
        }

        $_limit = $this->limit;
        if (!empty($_limit)) {
            $_offset = $this->offset;
            $limit = "LIMIT {$_offset}, {$_limit}";
        }

        return sprintf($template, $this->table, $where, $limit);
    }

    public function table($table, $fields = ["*"]): QueryInterface
    {
        if (empty($table)) {
            throw new Exception\ArgumentException("Invalid argument");
        }

        $this->table = $table;

        if ($fields) {
            $this->fields[$table] = $fields;
        }

        return $this;
    }

    public function from($table, $fields = ["*"]): QueryInterface
    {
        return $this->table($table, $fields);
    }

    public function join($join, $on, $fields = []): QueryInterface
    {
        if (empty($join)) {
            throw new Exception\ArgumentException("Invalid argument");
        }

        if (empty($on)) {
            throw new Exception\ArgumentException("Invalid argument");
        }

        $this->fields += [$join => $fields];
        $this->join[] = "JOIN {$join} ON {$on}";

        return $this;
    }

    public function leftJoin($join, $on, $fields = []): QueryInterface
    {
        if (empty($join)) {
            throw new Exception\ArgumentException("Invalid argument");
        }

        if (empty($on)) {
            throw new Exception\ArgumentException("Invalid argument");
        }

        $this->fields += [$join => $fields];
        $this->join[] = "LEFT JOIN {$join} ON {$on}";

        return $this;
    }

    public function rightJoin($join, $on, $fields = []): QueryInterface
    {
        if (empty($join)) {
            throw new Exception\ArgumentException("Invalid argument");
        }

        if (empty($on)) {
            throw new Exception\ArgumentException("Invalid argument");
        }

        $this->fields += [$join => $fields];
        $this->join[] = "RIGHT JOIN {$join} ON {$on}";

        return $this;
    }

    public function crossJoin($join): QueryInterface
    {
        if (empty($join)) {
            throw new Exception\ArgumentException("Invalid argument");
        }

        $this->join[] = "CROSS JOIN {$join}";

        return $this;
    }

    public function order($order): QueryInterface
    {
        if (empty($order)) {
            throw new Exception\ArgumentException("Invalid argument");
        }

        if (empty($this->order)) {
            $this->order = $order;
            return $this;
        }
        $this->order[] = $order;
        return $this;
    }

    public function where(): QueryInterface
    {
        $arguments = func_get_args();

        // allow where queries with numeric keys|clause by
        // replacing the value for the numeric key|clause
        if (is_int($arguments[0])) {
            $arguments[0] = $arguments[1];
        }
        if (sizeof($arguments) == 1) {
            $this->where[] = $arguments[0];
            return $this;
        }

        if (sizeof($arguments) < 1) {
            throw new Exception\ArgumentException("Invalid argument");
        }

        $arguments[0] = preg_replace("#\?#", "%s", $arguments[0]);

        foreach (array_slice($arguments, 1, null, true) as $parameter) {
            $arguments2 = [];
            if (!is_array($parameter)) {
                $parameter = [$parameter];
            }
            foreach ($parameter as $param) {
                $formattedParam = $param;
                if ($param instanceof \DateTime) {
                    switch ($this->connector->getDateTimeMode()) {
                        case 'DATETIME':
                            $formattedParam = $param->format('Y-m-d H:i:s');
                            break;
                        case 'TIMESTAMP':
                            $formattedParam = $param->getTimestamp();
                            break;
                    }
                }
                $arguments2[] = $this->_quote($formattedParam);
            }
            $arguments = array_merge([$arguments[0]], $arguments2);
        }

        $this->where[] = call_user_func_array("sprintf", $arguments);

        return $this;
    }

    public function limit(int $limit, ?int $page = 1): QueryInterface
    {
        if (empty($page) || $page < 1) {
            $page = 1;
        }

        $this->limit = $limit;
        $this->offset = $limit * ($page - 1);

        return $this;
    }

    public function count(): int
    {
        $limit = $this->limit;
        $offset = $this->offset;
        $fields = $this->fields;

        $this->fields = [$this->table => ["COUNT(1)" => "rowsCount"]];

        $this->limit(1);
        $row = $this->first();

        $this->fields = $fields;

        if ($fields) {
            $this->fields = $fields;
        }
        if ($limit) {
            $this->limit = $limit;
        }
        if ($offset) {
            $this->offset = $offset;
        }

        return $row["rowsCount"];
    }

    public function first()
    {
        $limit = $this->limit;
        $offset = $this->offset;

        $this->limit(1);

        $all = $this->all();
        $first = ArrayMethods::first($all);

        if ($limit) {
            $this->limit = $limit;
        }
        if ($offset) {
            $this->offset = $offset;
        }

        return $first;
    }

    protected function _getExceptionForImplementation($method)
    {
        return new Exception\ImplementationException("{$method} method not implemented");
    }

    protected function _buildSelect()
    {
        $fields = [];
        $where = $order = $limit = $join = "";
        $template = "SELECT %s FROM %s %s %s %s %s";

        foreach ($this->fields as $table => $_fields) {
            foreach ($_fields as $field => $alias) {
                if (is_string($field)) {
                    $fields[] = "{$field} AS {$alias}";
                } else {
                    $fields[] = $alias;
                }
            }
        }

        $fields = join(", ", $fields);

        $_join = $this->join;
        if (!empty($_join)) {
            $join = join(" ", $_join);
        }

        $_where = $this->where;
        if (!empty($_where)) {
            $joined = join(" AND ", $_where);
            $where = "WHERE {$joined}";
        }

        $orderArray = [];
        if (is_array($this->order)) {
            foreach ($this->order as $thisKey => $thisOrder) {
                if (is_string($thisKey)) {
                    $orderArray[] = "{$thisKey} {$thisOrder}";
                } else {
                    $orderArray[] = "{$thisOrder}";
                }
            }
            $_order = implode(", ", $orderArray);
        } else {
            $_order = $this->order;
        }
        if (!empty($_order)) {
            $order = "ORDER BY {$_order}";
        }

        $_limit = $this->limit;
        if (!empty($_limit)) {
            $_offset = $this->offset;

            if ($_offset) {
                $limit = "LIMIT {$_offset}, {$_limit}";
            } else {
                $limit = "LIMIT {$_limit}";
            }
        }
        return sprintf($template, $fields, $this->table, $join, $where, $order, $limit);
    }
}

<?php

namespace TeraBlaze\Ripana\ORM;

use DateTime;
use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Config\PolymorphismTrait;
use TeraBlaze\Container\Container;
use TeraBlaze\Inspector;
use TeraBlaze\Ripana\Database\Connection\ConnectionInterface;
use TeraBlaze\Ripana\Database\QueryBuilder\QueryBuilderInterface;
use TeraBlaze\Ripana\ORM\Column\Column;
use TeraBlaze\Ripana\ORM\Column\ManyToOne;
use TeraBlaze\Ripana\ORM\Column\OneToMany;
use TeraBlaze\Ripana\ORM\Exception\Connector as ConnectorException;
use TeraBlaze\Ripana\ORM\Exception\Primary;
use TeraBlaze\Ripana\ORM\Exception\PropertyException;

abstract class NewModel implements ModelInterface
{
    public const DATA_TYPES = [
        'autonumber' => 'autonumber',
        'text' => 'text',
        'integer' => 'integer',
        'decimal' => 'decimal',
        'boolean' => 'boolean',
        'bool' => 'bool',
        'datetime' => 'datetime',
    ];

    public const DATE_TYPES = ['date', 'time', 'datetime', 'timestamp', 'year'];

    public const truesy = [
        1, "1", "true", true, "yes", "yeah", "yup", "yupp", "y"
    ];
    public const falsy = [
        0, "0", "false", false, "no", "nope", "nah", "neh", "n"
    ];

    /** @var array $__syncSQL */
    private $__syncSQL = [];

    use PolymorphismTrait;

    public function __construct($initData = [])
    {
        if (is_array($initData) && (!empty($initData))) {
            $this->initData($initData);
        }
    }

    public static function __callStatic(string $method, array $parameters = [])
    {
        return static::query()->$method(...$parameters);
    }

    /**
     * Maps associative array to object properties
     *
     * @param array<string, mixed> $initData
     */
    protected function initData(array $initData): void
    {
        if (empty($initData)) {
            return;
        }
        foreach ($initData as $key => $value) {
            try {
                $prop = $this->_getInitProp($key);
            } catch (PropertyException $propertyException) {
                // TODO: Add a logger to log the exception
                continue;
            }
            // Get key to search in self::__columns
            if ((!isset($this->_getColumns()[$key])) && isset($this->_getColumnsReverseMap()[$key])) {
                $key = $this->_getColumnsReverseMap()[$key];
            }
            if (
                in_array(mb_strtolower($this->_getColumns()[$key]['type']), ['date', 'time', 'datetime'], true) &&
                (!$value instanceof DateTime) &&
                (!empty($value)) &&
                $this->_getColumns()[$key]['autoconvert'] != false
            ) {
                try {
                    $value = new DateTime($value);
                } catch (\Exception $exception) {
                }
            }
            $this->$prop = $value;
        }
    }

    /**
     * @param string $key
     * @return string
     * @throws PropertyException
     */
    private function _getInitProp(string $key): string
    {
        if (isset($this->_getColumns()[$key])) {
            return $this->_getColumns()[$key]['raw'];
        }
        if (isset($this->_getColumnsReverseMap()[$key])) {
            return $key;
        }
        throw new PropertyException("Entity property with property name or column name '{$key}' not found");
    }

    /**
     * @return QueryBuilderInterface
     * @throws ConnectorException
     */
    public static function query(): QueryBuilderInterface
    {
        $model = new static();
        return $model->_getConnection()->getQueryBuilder()->from($model->_getTable());
    }

    public function save()
    {
        $primary = $this->_getPrimaryColumn();
        $primaryRaw = $primary["raw"];
        $primaryName = $primary["name"];
        $query = static::query();
        if (!empty($this->$primaryRaw)) {
            $query->where("$primaryName = :{$primaryName}Where")
                ->setParameter("{$primaryName}Where", $this->$primaryRaw);
        }
        $data = [];
        $params = [];
        foreach ($this->_getColumns() as $key => $column) {
            $prop = $column["raw"];
            if ($column != $primary && $column) {
                $datum = $this->saveDatum($prop, $column);
                if (is_null($datum) && $column['nullable'] == false) {
                    continue;
                }
                $data[$key] = ":$key";
                $params[$key] = $datum;
            }
        }
        $result = $query->save($data, $params)->execute();
        if ($query->getType() === QueryBuilderInterface::INSERT) {
            return $query->getLastInsertId();
        }
        return $result !== false;
    }

    /**
     * @param string $prop
     * @param string[] $column
     * @return DateTime|int|mixed|string|null
     * @throws ConnectorException
     */
    private function saveDatum(string $prop, array $column)
    {
        if (in_array(strtolower($column['type']), self::DATE_TYPES)) {
            $datum = $this->$prop ?? null;
        } else {
            $datum = $this->$prop ?? $column['default'] ?? null;
        }
        if ($datum instanceof DateTime && $column['autoconvert'] != false) {
            $dateTimeMode = $this->_getConnection()->getDateTimeMode();
            if ($dateTimeMode == 'TIMESTAMP') {
                $datum = $datum->getTimestamp();
            } elseif ($dateTimeMode == 'DATETIME') {
                switch ($column['type']) {
                    case 'date':
                        $datum = $datum->format('Y-m-d');
                        break;
                    case 'time':
                        $datum = $datum->format('H:i:s.u');
                        break;
                    case 'datetime':
                        $datum = $datum->format('Y-m-d H:i:s.u');
                        break;
                }
            }
        }
        return $datum;
    }

    public function delete()
    {
        $primary = $this->_getPrimaryColumn();
        $primaryRaw = $primary["raw"];
        $primaryName = $primary["name"];
        if (!empty($this->$primaryRaw)) {
            return $this->_getConnection()
                ->getQueryBuilder()
                ->delete($this->_getTable())
                ->where("{$primaryName} = :id")
                ->setParameter("id", $this->$primaryRaw)
                ->execute();
        }
        return null;
    }

    public static function deleteAll($where = '', $parameters = [])
    {
        $instance = new static();
        $query = $instance->_getConnection()
            ->getQueryBuilder()
            ->delete($instance->_getTable());
        $instance->buildWhereQuery($where, $parameters, $query);
        return $query->execute()->rowCount();
    }

    public static function all(
        $where = '',
        $parameters = [],
        $fields = ["*"],
        $order = [],
        $limit = null,
        $offset = null,
        $page = null
    ): ?EntityCollection {
        $model = new static();
        if (empty($fields)) {
            $fields = ['*'];
        }
        return $model->_all($where, $parameters, $fields, $order, $limit, $offset, $page);
    }

    protected function _all($where = [], $parameters = [], $fields = ["*"], $orderList = [], $limit = null, $offset = null, $page = null)
    {
        $fields = ArrayMethods::wrap($fields);
        $query = $this
            ->_getConnection()
            ->getQueryBuilder()
            ->select(...$fields)
            ->from($this->_getTable());
        $this->buildWhereQuery($where, $parameters, $query);
        $this->buildOrderQuery($orderList, $query);
        $this->buildLimitQuery($limit, $offset, $page, $query);
        $rows = $query->all();
        $objectRows = [];
        // TODO: Implement fetching relationships
        //$primaryKey = $this->_getPrimaryColumn()['name'];
        // $primaryKeys = array_column($rows, $primaryKey);
        foreach ($rows as $row) {
            $object = clone $this;
            $object->initData($row);
            $objectRows[] = $object;
            $object = null;
        }
        return new EntityCollection($objectRows, static::class);
    }

    public static function first(
        $where = '',
        $parameters = [],
        $fields = ["*"],
        $order = []
    ): ?self {
        $model = new static();
        if (empty($fields)) {
            $fields = ['*'];
        }
        return $model->_first($where, $parameters, $fields, $order);
    }

    protected function _first(
        $where = '',
        $parameters = [],
        $fields = ["*"],
        $orderList = []
    ): ?self {
        if (is_string($fields)) {
            $fields = [$fields];
        }
        $query = $this
            ->_getConnection()
            ->getQueryBuilder()
            ->select(...$fields)
            ->from($this->_getTable());
        $this->buildWhereQuery($where, $parameters, $query);
        $this->buildOrderQuery($orderList, $query);
        $first = $query->first();
        if ($first) {
            $this->initData($first);
            return $this;
        }
        return null;
    }

    public static function find($modelId): ?self
    {
        $model = new static();
        $primaryKey = $model->_getPrimaryColumn()['name'];
        return $model->_first([
            "{$primaryKey} = ?" => $modelId,
        ]);
    }

    public static function count($where = '', $parameters = [])
    {
        $model = new static();
        return $model->_count($where, $parameters);
    }

    protected function _count($where = '', $parameters = [])
    {
        $query = $this
            ->_getConnection()
            ->getQueryBuilder()
            ->from($this->_getTable());
        $this->buildWhereQuery($where, $parameters, $query);
        return $query->count();
    }

    public function __clone()
    {
        $primary = $this->_getPrimaryColumn();
        $primaryRaw = $primary["raw"];

        unset($this->$primaryRaw);
    }

    private function _getInspector(): Inspector
    {
        static $inspector;
        if (is_null($inspector)) {
            $inspector = new Inspector($this);
        }
        return $inspector;
    }

    private function _getContainer(): Container
    {
        return Container::getContainer();
    }

    public function _getDbConf()
    {
        static $dbConf;
        if (is_null($dbConf)) {
            $classMeta = $this->_getInspector()->getClassMeta();
            $dbConf = $classMeta['@dbConf'][0] ?? 'default';
        }
        return $dbConf;
    }

    public function _getTable()
    {
        static $table;
        if (is_null($table)) {
            $classMeta = $this->_getInspector()->getClassMeta();
            $table = $classMeta['@table']['name'] ?? $classMeta['@table'][0] ??
                $this->_getInspector()->getClassName();
        }
        return $table;
    }

    public function _getConnection(): ConnectionInterface
    {
        static $connection;

        $connectionString = 'ripana.database.connection.' . $this->_getDbConf();
        if (empty($connection)) {
            if ($this->_getContainer()->has($connectionString)) {
                $database = $this->_getContainer()->get($connectionString);
            } else {
                throw new ConnectorException("PDOConnection: {$connectionString} not found");
            }
            $connection = $database;
        }
        return $connection;
    }

    public function _getColumns()
    {
        static $columns;

        if (empty($columns)) {
            $primaries = 0;
            $columns = [];
            $class = get_class($this);
            $properties = $this->_getInspector()->getClassProperties();

            foreach ($properties as $property) {
                $propertyMeta = $this->_getInspector()->getPropertyMeta($property);
                if (!empty($propertyMeta['@column'])) {
                    $primary = !empty($propertyMeta['@primary']);
                    if ($primary) {
                        $primaries++;
                    }
                    $column = (new Column($propertyMeta))->getColumn($property);
                    $name = $column['name'];
                    $columns[$name] = $column;
                }
                if (!empty($propertyMeta['@column/OneToMany']) || !empty($propertyMeta['@column\OneToMany'])) {
                    $primary = !empty($propertyMeta['@primary']);
                    if ($primaries > 1) {
                        throw new Primary("A foreign key cannot be used as a primary column");
                    }
                    $column = (new OneToMany($propertyMeta))->getColumn($property);
                    $name = $column['name'];
                    $columns[$name] = $column;
                }
                if (!empty($propertyMeta['@column/ManyToOne']) || !empty($propertyMeta['@column\ManyToOne'])) {
                    $primary = !empty($propertyMeta['@primary']);
                    if ($primaries > 1) {
                        throw new Primary("A foreign key cannot be used as a primary column");
                    }
                    $column = (new ManyToOne($propertyMeta))->getColumn($property);
                    $name = $column['name'];
                    $columns[$name] = $column;
                }
            }
            if ($primaries > 1) {
                throw new Primary("{$class} cannot have more than once primary column");
            }
        }
        return $columns;
    }

    protected function _getColumnsReverseMap(): array
    {
        static $columnsReverseMap;

        if (is_null($columnsReverseMap)) {
            foreach ($this->_getColumns() as $key => $column) {
                $columnsReverseMap[$column['raw']] = $key;
            }
        }
        return $columnsReverseMap;
    }

    public function _getColumn(string $name): ?string
    {
        if (!empty($this->_getColumns()[$name])) {
            return $this->_getColumns()[$name];
        }
        return null;
    }

    public function _getPrimaryColumn()
    {
        static $primary;
        if (is_null($primary)) {
            foreach ($this->_getColumns() as $column) {
                if ($column['primary']) {
                    $primary = $column;
                    break;
                }
            }
        }
        return $primary;
    }

    /**
     * Builds the where query
     *
     * @param string $where
     * @param array<string|int, string> $parameters
     * @param QueryBuilderInterface $query
     */
    private function buildWhereQuery(string $where, array $parameters, QueryBuilderInterface $query)
    {
        if (!empty($where)) {
            $query->where($where)
                ->setParameters($parameters);
        }
    }

    /**
     * Builds the order query
     *
     * @param array<string|int, string> $orderList
     * @param QueryBuilderInterface $query
     */
    private function buildOrderQuery(array $orderList, QueryBuilderInterface $query)
    {
        foreach ($orderList as $sort => $order) {
            if (is_int($sort)) {
                $sort = $order;
                $order = null;
            }
            $query->addOrderBy($sort, $order);
        }
    }

    /**
     * Builds the limit query
     *
     * @param $limit
     * @param $offset
     * @param $page
     * @param QueryBuilderInterface $query
     */
    private function buildLimitQuery($limit, $offset, $page, QueryBuilderInterface $query)
    {
        if ($limit != null) {
            if (!is_null($offset)) {
                $query->limit($limit, $offset);
            } elseif (!is_null($page)) {
                $query->limitByPage($limit, $page);
            } else {
                $query->limit($limit);
            }
        }
    }

    public function storeSyncSQL($queries)
    {
        if (!array($queries)) {
            $queries = [$queries];
        }
        $this->__syncSQL = $queries;
    }

    public function retrieveSyncSQL()
    {
        return $this->__syncSQL;
    }
}

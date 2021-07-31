<?php

namespace TeraBlaze\Ripana\ORM;

use DateTime;
use TeraBlaze\Config\PolymorphismTrait;
use TeraBlaze\Container\Container;
use TeraBlaze\Inspector;
use TeraBlaze\Ripana\Database\Connectors\ConnectorInterface;
use TeraBlaze\Ripana\ORM\Column\Column;
use TeraBlaze\Ripana\ORM\Column\ManyToOne;
use TeraBlaze\Ripana\ORM\Column\OneToMany;
use TeraBlaze\Ripana\ORM\Exception\Connector as ConnectorException;
use TeraBlaze\Ripana\ORM\Exception\Primary;
use TeraBlaze\Ripana\ORM\Exception\PropertyException;

abstract class Model
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

    /** @var string $table */
    private $__table;

    /** @var ConnectorInterface $__connector */
    private $__connector;

    private $__columns;
    private $__columnsReverseMap;
    private $__primary;

    /** @var Inspector $__inspector */
    private $__inspector;

    /** @var Container $__inspector */
    private $__container;

    /** @var string $__dbConf */
    private $__dbConf;

    /** @var array $__syncSQL */
    private $__syncSQL = [];

    use PolymorphismTrait;

    public function __construct($initData = [])
    {
        if (is_array($initData) && (!empty($initData))) {
            $this->initData($initData);
        }
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
            if ((!isset($this->_getColumns()[$key])) && isset($this->__getColumnsReverseMap()[$key])) {
                $key = $this->__getColumnsReverseMap()[$key];
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
        } elseif (isset($this->__getColumnsReverseMap()[$key])) {
            return $key;
        }
        throw new PropertyException("Entity property with property name or column name '{$key}' not found");
    }

    public function save()
    {
        $primary = $this->_getPrimaryColumn();
        $primaryRaw = $primary["raw"];
        $primaryName = $primary["name"];
        $query = $this->_getConnector()
            ->query()
            ->from($this->_getTable());
        if (!empty($this->$primaryRaw)) {
            $query->where("{$primaryName} = ?", $this->$primaryRaw);
        }
        $data = array();
        foreach ($this->_getColumns() as $key => $column) {
            $prop = $column["raw"];
            if ($column != $primary && $column) {
                $datum = $this->saveDatum($prop, $column);
                if (is_null($datum) && $column['nullable'] == false) {
                    continue;
                }
                $data[$key] = $datum;
                continue;
            }
        }
        $result = $query->save($data);
        if ($result !== true) {
            $this->$primaryRaw = $result;
        }
        return $result;
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
            $datum = $this->$prop ?? ($column['default'] ?? '');
        }
        if ($datum instanceof DateTime && $column['autoconvert'] != false) {
            $dateTimeMode = $this->_getConnector()->getDateTimeMode();
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
            return $this->_getConnector()
                ->query()
                ->from($this->_getTable())
                ->where("{$primaryName} = ?", $this->$primaryRaw)
                ->delete();
        }
        return null;
    }

    public static function deleteAll($where = array())
    {
        $instance = new static();
        $query = $instance->_getConnector()
            ->query()
            ->from($instance->_getTable());
        foreach ($where as $clause => $value) {
            $query->where($clause, $value);
        }
        return $query->delete();
    }

    public static function all(
        $where = array(),
        $fields = array("*"),
        $order = null,
        $limit = null,
        $page = null
    ): ?EntityCollection {
        $model = new static();
        return $model->_all($where, $fields, $order, $limit, $page);
    }

    protected function _all($where = [], $fields = ["*"], $order = null, $limit = null, $page = null)
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }
        $query = $this
            ->_getConnector()
            ->query()
            ->from($this->_getTable(), $fields);
        foreach ($where as $clause => $value) {
            $query->where($clause, $value);
        }
        if ($order != null) {
            $query->order($order);
        }
        if ($limit != null) {
            $query->limit($limit, $page);
        }
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
        $where = [],
        $fields = ["*"],
        $order = null
    ): ?self {
        $model = new static();
        return $model->_first($where, $fields, $order);
    }

    protected function _first(
        $where = [],
        $fields = ["*"],
        $order = null
    ): ?self {
        if (is_string($fields)) {
            $fields = [$fields];
        }
        $query = $this
            ->_getConnector()
            ->query()
            ->from($this->_getTable(), $fields);
        foreach ($where as $clause => $value) {
            $query->where($clause, $value);
        }
        if ($order != null) {
            $query->order($order);
        }
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

    public static function count($where = array())
    {
        $model = new static();
        return $model->_count($where);
    }

    protected function _count($where = array())
    {
        $query = $this
            ->_getConnector()
            ->query()
            ->from($this->_getTable());
        foreach ($where as $clause => $value) {
            $query->where($clause, $value);
        }
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
        return $this->__inspector = $this->__inspector ?: new Inspector($this);
    }

    private function _getContainer(): Container
    {
        return $this->__container = $this->__container ?: Container::getContainer();
    }

    public function _getDbConf()
    {
        if (empty($this->__dbConf)) {
            $classMeta = $this->_getInspector()->getClassMeta();
            $this->__dbConf = $classMeta['@dbConf'][0] ?? 'default';
        }
        return $this->__dbConf;
    }

    public function _getTable()
    {
        if (empty($this->__table)) {
            $classMeta = $this->_getInspector()->getClassMeta();
            $this->__table = $classMeta['@table']['name'] ?? $classMeta['@table'][0] ??
                $this->_getInspector()->getClassName();
        }
        return $this->__table;
    }

    public function _getConnector(): ConnectorInterface
    {
        $connectorString = 'ripana.database.connector.' . $this->_getDbConf();
        if (empty($this->__connector)) {
            if ($this->_getContainer()->has($connectorString)) {
                $database = $this->_getContainer()->get($connectorString);
            } else {
                throw new ConnectorException("MysqliConnector: {$connectorString} not found");
            }
            $this->__connector = $database;
        }
        return $this->__connector;
    }

    public function _getDatabase(): ConnectorInterface
    {
        return $this->_getConnector();
    }

    public function _getColumns()
    {
        if (empty($this->__columns)) {
            $primaries = 0;
            $columns = [];
            $columnsReverseMap = [];
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
                    $columnsReverseMap[$property] = $name;
                }
                if (!empty($propertyMeta['@column/OneToMany']) || !empty($propertyMeta['@column\OneToMany'])) {
                    $primary = !empty($propertyMeta['@primary']);
                    if ($primaries > 1) {
                        throw new Primary("A foreign key cannot be used as a primary column");
                    }
                    $column = (new OneToMany($propertyMeta))->getColumn($property);
                    $name = $column['name'];
                    $columns[$name] = $column;
                    $columnsReverseMap[$property] = $name;
                }
                if (!empty($propertyMeta['@column/ManyToOne']) || !empty($propertyMeta['@column\ManyToOne'])) {
                    $primary = !empty($propertyMeta['@primary']);
                    if ($primaries > 1) {
                        throw new Primary("A foreign key cannot be used as a primary column");
                    }
                    $column = (new ManyToOne($propertyMeta))->getColumn($property);
                    $name = $column['name'];
                    $columns[$name] = $column;
                    $columnsReverseMap[$property] = $name;
                }
            }
            if ($primaries > 1) {
                throw new Primary("{$class} cannot have more than once primary column");
            }
            $this->__columnsReverseMap = $columnsReverseMap;
            $this->__columns = $columns;
        }
        return $this->__columns;
    }

    protected function _getColumnsReverseMap()
    {
        if (empty($this->__columnsReverseMap)) {
            $this->_getColumns();
        }
        return $this->__columnsReverseMap;
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
        if (!isset($this->__primary)) {
            $primary = '';
            foreach ($this->_getColumns() as $column) {
                if ($column['primary']) {
                    $primary = $column;
                    break;
                }
            }
            $this->__primary = $primary;
        }
        return $this->__primary;
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

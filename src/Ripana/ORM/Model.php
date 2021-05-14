<?php

namespace TeraBlaze\Ripana\ORM;

use DateTime;
use TeraBlaze\Configuration\PolymorphismTrait;
use TeraBlaze\Container\Container;
use TeraBlaze\Inspector;
use TeraBlaze\Ripana\Database\ConnectorInterface;
use TeraBlaze\Ripana\ORM\Column\Column;
use TeraBlaze\Ripana\ORM\Column\ManyToOne;
use TeraBlaze\Ripana\ORM\Column\OneToMany;
use TeraBlaze\Ripana\ORM\Exception\Connector as ConnectorException;
use TeraBlaze\Ripana\ORM\Exception\Implementation;
use TeraBlaze\Ripana\ORM\Exception\Primary;
use TeraBlaze\Ripana\ORM\Exception\Property;

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
    private $__dbConf = "default";

    /** @var array $__syncSQL */
    private $__syncSQL = [];

    use PolymorphismTrait;

    public function __construct($initData = null)
    {
        $this->__inspector = new Inspector($this);
        $this->__columns = $this->getColumns();
        $this->__container = Container::getContainer();
        $this->__connector = $this->getConnector();
        $this->__table = $this->getTable();
        $this->__primary = $this->getPrimaryColumn();
        if (is_array($initData) && (!empty($initData))) {
            $this->initData($initData);
        }
    }

    public function __clone()
    {
        $primary = $this->__primary;
        $primaryRaw = $primary["raw"];

        unset($this->$primaryRaw);
    }

    /**
     * Maps associative array to object properties
     *
     * @param array $initData
     */
    protected function initData(array $initData): void
    {
        if (is_null($initData)) {
            return;
        }
        foreach ($initData as $key => $value) {
            try {
                $prop = $this->getInitProp($key);
            } catch (Property $propertyxception) {
                // TODO: Add a logger to log the exception
                continue;
            }
            // Get key to search in self::__columns
            if ((!isset($this->__columns[$key])) && isset($this->__columnsReverseMap[$key])) {
                $key = $this->__columnsReverseMap[$key];
            }
            if (
                in_array(mb_strtolower($this->__columns[$key]['type']), ['date', 'time', 'datetime'], true) &&
                (!$value instanceof DateTime) &&
                (!empty($value)) &&
                $this->__columns[$key]['autoconvert'] != false
            ) {
                try {
                    $value = new DateTime($value);
                } catch (\Exception $exception) {
                }
            }
            $this->$prop = $value;
        }
        return;
    }

    private function getInitProp(string $key): string
    {
        if (isset($this->__columns[$key])) {
            return $this->__columns[$key]['raw'];
        } else if (isset($this->__columnsReverseMap[$key])) {
            return $key;
        }
        throw new Property("Entity property with property name or column name '{$key}' not found");
    }

    public function save()
    {
        $primary = $this->__primary;
        $primaryRaw = $primary["raw"];
        $primaryName = $primary["name"];
        $query = $this->__connector
            ->query()
            ->from($this->__table);
        if (!empty($this->$primaryRaw)) {
            $query->where("{$primaryName} = ?", $this->$primaryRaw);
        }
        $data = array();
        foreach ($this->__columns as $key => $column) {
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

    private function saveDatum($prop, $column)
    {
        $datum = $this->$prop;
        if ($datum instanceof DateTime && $column['autoconvert'] != false) {
            $dateTimeMode = $this->getConnector()->getDateTimeMode();
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
        $primary = $this->__primary;
        $primaryRaw = $primary["raw"];
        $primaryName = $primary["name"];
        if (!empty($this->$primaryRaw)) {
            return $this->__connector
                ->query()
                ->from($this->__table)
                ->where("{$primaryName} = ?", $this->$primaryRaw)
                ->delete();
        }
        return null;
    }

    public static function deleteAll($where = array())
    {
        $instance = new static();
        $query = $instance->__connector
            ->query()
            ->from($instance->__table);
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
            ->__connector
            ->query()
            ->from($this->__table, $fields);
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
        //$primaryKey = $this->getPrimaryColumn()['name'];
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
            ->__connector
            ->query()
            ->from($this->__table, $fields);
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
        $primaryKey = $model->getPrimaryColumn()['name'];
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
            ->__connector
            ->query()
            ->from($this->__table);
        foreach ($where as $clause => $value) {
            $query->where($clause, $value);
        }
        return $query->count();
    }

    public function _getExceptionForImplementation($method)
    {
        return new Implementation('{$method} method not implemented');
    }

    public function getTable()
    {
        if (empty($this->__table)) {
            $classMeta = $this->__inspector->getClassMeta();
            $this->__table = $classMeta['@table']['name'] ?? $classMeta['@table'][0] ??
                $this->__inspector->getClassName();
        }
        return $this->__table;
    }

    public function getConnector(): ConnectorInterface
    {
        $connectorString = 'ripana.database.connector.' . $this->__dbConf;
        if (empty($this->__connector)) {
            if ($this->__container->has($connectorString)) {
                $database = $this->__container->get($connectorString);
            } else {
                throw new ConnectorException("Connector: {$connectorString} not found");
            }
            $this->__connector = $database;
        }
        return $this->__connector;
    }

    public function getDatabase(): ConnectorInterface
    {
        return $this->getConnector();
    }

    public function getColumns()
    {
        if (empty($this->__columns)) {
            $primaries = 0;
            $columns = [];
            $columnsReverseMap = [];
            $class = get_class($this);
            $properties = $this->__inspector->getClassProperties();

            foreach ($properties as $property) {
                $propertyMeta = $this->__inspector->getPropertyMeta($property);
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

    public function getColumn(string $name): ?string
    {
        if (!empty($this->__columns[$name])) {
            return $this->__columns[$name];
        }
        return null;
    }

    public function getPrimaryColumn()
    {
        if (!isset($this->__primary)) {
            $primary = '';
            foreach ($this->__columns as $column) {
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

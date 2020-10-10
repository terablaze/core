<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/1/2017
 * Time: 4:39 AM
 */

namespace TeraBlaze\Ripana\ORM;

use TeraBlaze\Base as Base;
use TeraBlaze\Collections\ArrayCollection;
use TeraBlaze\Configuration\PolymorphismTrait;
use TeraBlaze\Container\Container;
use TeraBlaze\Inspector;
use TeraBlaze\Ripana\Database\Connector\Connector;
use TeraBlaze\Ripana\ORM\Column\Column;
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
    protected $__table;

    /** @var Connector $__connector */
    protected $__connector;

    protected $__columns;
    protected $__columnsReverseMap;
    protected $__primary;

    /** @var Inspector $__inspector */
    protected $__inspector;

    /** @var Container $__inspector */
    protected $__container;

    /** @var string $__dbConf */
    protected $__dbConf = "default";

    use PolymorphismTrait;

    public function __construct($initData = null)
    {
        $this->__inspector = new Inspector($this);
        $this->__columns = $this->getColumns();
        $this->__container = Container::getContainer();
        $this->__connector = $this->getConnector();
        $this->__table = $this->getTable();
        $this->__primary = $this->getPrimaryColumn();
        $this->initData($initData);
        $this->load();
    }

    protected function initData($initData): void
    {
        if (is_null($initData)) {
            return;
        }
        if (is_array($initData) && count($initData) > 1) {
            foreach ($initData as $key => $value) {
                if (isset($this->__columns[$key])) {
                    $prop = $this->__columns[$key]['raw'];
                } else if (isset($this->__columnsReverseMap[$key])) {
                    $prop = $key;
                } else {
                    throw new Property("Entity property with property name or column name '{$key}' not found");
                }
                if (!empty($initData[$key]) && !isset($this->$prop)) {
                    $this->$prop = $value;
                }
            }
            return;
        }
        $raw = $this->__primary["raw"];
        $this->$raw = $initData;
        return;
    }

    protected function forceInitData($initData): void
    {
        if (is_null($initData)) {
            return;
        }
        foreach ($initData as $key => $value) {
            if (isset($this->__columns[$key])) {
                $prop = $this->__columns[$key]['raw'];
            } else if (isset($this->__columnsReverseMap[$key])) {
                $prop = $key;
            } else {
                throw new Property("Entity property with property name or column name '{$key}' not found");
            }
            $this->$prop = $value;
        }
        return;
    }

    public function load()
    {
        $primary = $this->__primary;
        $raw = $primary["raw"];
        $name = $primary["name"];
        if (!empty($this->$raw)) {
            $previous = $this->__connector
                ->query()
                ->from($this->__table)
                ->where("{$name} = ?", $this->$raw)
                ->first();
            if ($previous == null) {
                throw new Primary("No record on the table {$this->__table} of entity class " .
                    get_class($this) . " found with primary key {$this->$raw}");
            }
            foreach ($previous as $key => $value) {
                $prop = $this->__columns[$key]['raw'];
                if (!empty($previous[$key]) && !isset($this->$prop)) {
                    $this->$prop = $value;
                }
            }
        }
    }

    public function save()
    {
        $primary = $this->__primary;
        $raw = $primary["raw"];
        $name = $primary["name"];
        $query = $this->__connector
            ->query()
            ->from($this->__table);
        if (!empty($this->$raw)) {
            $query->where("{$name} = ?", $this->$raw);
        }
        $data = array();
        foreach ($this->__columns as $key => $column) {
            $prop = $column["raw"];
            if ($column != $primary && $column) {
                $method = "get" . ucfirst($prop);
                $data[$key] = $this->$method();
                continue;
            }
        }
        $result = $query->save($data);
        if ($result !== true) {
            $this->$raw = $result;
        }
        return $result;
    }

    public function delete()
    {
        $primary = $this->__primary;
        $raw = $primary["raw"];
        $name = $primary["name"];
        if (!empty($this->$raw)) {
            return $this->__connector
                ->query()
                ->from($this->__table)
                ->where("{$name} = ?", $this->$raw)
                ->delete();
        }
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
        $direction = null,
        $limit = null,
        $page = null
    ) {
        $model = new static();
        return $model->_all($where, $fields, $order, $direction, $limit, $page);
    }

    protected function _all($where = [], $fields = ["*"], $order = null, $direction = null, $limit = null, $page = null)
    {
        $query = $this
            ->__connector
            ->query()
            ->from($this->__table, $fields);
        foreach ($where as $clause => $value) {
            $query->where($clause, $value);
        }
        if ($order != null) {
            $query->order($order, $direction);
        }
        if ($limit != null) {
            $query->limit($limit, $page);
        }
        $rows = array();
        foreach ($query->all() as $row) {
            $object = clone $this;
            $object->forceInitData($row);
            $rows[] = $object;
            $object = null;
        }
        return new ArrayCollection($rows, static::class);
    }

    public static function first(
        $where = [],
        $fields = ["*"],
        $order = null,
        $direction = null
    ) {
        $model = new static();
        return $model->_first($where, $fields, $order, $direction);
    }

    protected function _first(
        $where = [],
        $fields = ["*"],
        $order = null,
        $direction = null
    ) {
        $query = $this
            ->__connector
            ->query()
            ->from($this->__table, $fields);
        foreach ($where as $clause => $value) {
            $query->where($clause, $value);
        }
        if ($order != null) {
            $query->order($order, $direction);
        }
        $first = $query->first();
        if ($first) {
            $this->initData($first);
            return $this;
        }
        return null;
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

    public function getConnector(): Connector
    {
        if (empty($this->__connector)) {
            $database = $this->__container->get('ripana.database.connector.' . $this->__dbConf);
            if (!$database) {
                throw new ConnectorException('No connector availible');
            }
            $this->__connector = $database;
        }
        return $this->__connector;
    }

    public function getDatabase()
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
                if (!empty($propertyMeta['@column/OneToMany'])) {
                    $primary = !empty($propertyMeta['@primary']);
                    if ($primaries > 1) {
                        throw new Primary("A foreign key cannot be used as a primary column");
                    }
                    $column = (new OneToMany($propertyMeta))->getColumn($property);
                    $name = $column['name'];
                    $columns[$name] = $column;
                    $columnsReverseMap[$property] = $name;
                }
            }
            if ($primaries > 1) {
                throw new Primary("{$class} cannot have more than once primary column");
            }
            $this->__columnsReverseMap = $columnsReverseMap;
            return $columns;
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
}

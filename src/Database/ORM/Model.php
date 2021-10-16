<?php

namespace TeraBlaze\Database\ORM;

use DateTime;
use Exception;
use TeraBlaze\Database\Exception\ConnectionException;
use TeraBlaze\Database\ORM\Exception\MappingException;
use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Config\PolymorphismTrait;
use TeraBlaze\Container\Container;
use TeraBlaze\Inspector;
use TeraBlaze\Database\Connection\ConnectionInterface;
use TeraBlaze\Database\Query\QueryBuilderInterface;
use TeraBlaze\Database\ORM\Exception\PropertyException;
use TeraBlaze\Support\StringMethods;
use Throwable;

abstract class Model implements ModelInterface
{
    use PolymorphismTrait;

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
                unset($prop);
                continue;
            }
            $this->_setPropertyValue($prop, $value);
        }
    }

    /**
     * @param string $key
     * @return string
     * @throws PropertyException
     */
    private function _getInitProp(string $key): string
    {
        try {
            return $this->_getClassMetadata()->getPropertyForColumn($key);
        } catch (MappingException $mappingException) {
            if (property_exists($this, $key)) {
                return $key;
            }
        }
        throw new PropertyException("Entity property with property name or column name '{$key}' not found");
    }

    /**
     * @param string $property
     * @param $value
     * @return void
     */
    private function _setPropertyValue(string $property, $value): void
    {
        if (
            in_array(
                StringMethods::lower($this->_getClassMetadata()->getPropertyType($property)),
                ['date', 'time', 'datetime'],
                true
            ) &&
            (!$value instanceof DateTime) &&
            (!empty($value)) &&
            ($this->_getClassMetadata()->getPropertyOptions($property)['convertDate'] ?? "" != false)
        ) {
            try {
                $value = new DateTime($value);
            } catch (Exception $exception) {
            }
        }
        try {
            $this->$property = $value;
        } catch (Throwable $throwable) {
        }
    }

    /**
     * @return QueryBuilderInterface
     */
    public static function query(): QueryBuilderInterface
    {
        $model = new static();
        return $model->_getConnection()->getQueryBuilder()->from($model->_getTable());
    }

    /**
     * @param array<int|string, mixed> $initData
     * return $this|null
     */
    public static function create(array $initData = [])
    {
        $model = new static();
        if (is_array($initData) && (!empty($initData))) {
            $model->initData($initData);
        }
        if ($model->save()) {
            $model->_loadAssociations($initData);
            return $model;
        }
        return null;
    }

    public function save()
    {
        [
            'primary' => $primary,
            'primaryProperty' => $primaryProperty,
            'primaryColumn' => $primaryColumn,
        ] = $this->_getPrimaryColumn();
        $query = static::query();
        if (!empty($this->$primaryProperty)) {
            $query->where("$primaryColumn = :{$primaryColumn}Where")
                ->setParameter("{$primaryColumn}Where", $this->$primaryProperty);
        }
        $data = [];
        $params = [];
        foreach ($this->_getClassMetadata()->getAllMappings() as $property => $mapping) {
            $type = $mapping['type'] ?? '';
            if (is_int($type) && $mapping['type'] & ClassMetadata::TO_MANY) {
                continue;
            }
            $queryName = $this->_getClassMetadata()->getColumnForProperty($property);
            if (false == ($mapping['id'] ?? false)) {
                if (!isset($this->$property)) {
                    continue;
                }
                $datum = $this->resolveDatum($property, $mapping);
                if (
                    is_null($datum) &&
                    ($mapping['nullable'] ?? $mapping['joinColumns'][0]['nullable'] ?? true == false)
                ) {
                    continue;
                }
                $data[$queryName] = ":$queryName";
                $params[$queryName] = $datum;
            }
        }
        $result = $query->save($data, $params);
        if ($query->getType() === QueryBuilderInterface::INSERT) {
            try {
                return $this->$primaryProperty = $query->getLastInsertId();
            } catch (Throwable $throwable) {
                // TODO: deal with this later
            }
        }
        return $result !== false;
    }

    private function resolveDatum(string $property, array $mapping)
    {
        $datum = $this->getSaveDatum($property, $mapping);
        if ($datum instanceof Model) {
            $mappingColumn = $this->_getClassMetadata()->getSingleAssociationReferencedJoinColumnName($property);
            $mappingProperty = $datum->_getClassMetadata()->getPropertyForColumn($mappingColumn);
            if (isset($datum->$mappingProperty)) {
                $datum = $datum->$mappingProperty;
            } else {
                $datum->save();
                $datum = $datum->$mappingProperty;
            }
        }
        return $datum;
    }

    /**
     * @param string $prop
     * @param string[] $column
     * @return DateTime|int|mixed|string|null
     */
    private function getSaveDatum(string $prop, array $column)
    {
        if (in_array(strtolower($column['type']), self::DATE_TYPES)) {
            $datum = $this->$prop ?? null;
        } else {
            $datum = $this->$prop ?? $column['options']['default'] ?? null;
        }
        if ($datum instanceof DateTime && ($column['options']['convertDate'] ?? '' != false)) {
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
        [
            'primary' => $primary,
            'primaryProperty' => $primaryProperty,
            'primaryColumn' => $primaryColumn,
        ] = $this->_getPrimaryColumn();
        if (!empty($this->$primaryProperty)) {
            return $this->_getConnection()
                ->getQueryBuilder()
                ->delete($this->_getTable())
                ->where("$primaryColumn = :id")
                ->setParameter("id", $this->$primaryProperty)
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

    protected function _all(
        $where = [],
        $parameters = [],
        $fields = ["*"],
        $orderList = [],
        $limit = null,
        $offset = null,
        $page = null
    )
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
            $object->_loadAssociations($row);
            $objectRows[] = $object;
            $object = null;
        }
        return new EntityCollection($objectRows, static::class);
    }

    /**
     * @param string $where
     * @param array $parameters
     * @param string[] $fields
     * @param array $order
     * @return $this|null
     */
    public static function first(
        $where = '',
        $parameters = [],
        $fields = ["*"],
        $order = []
    ) {
        $model = new static();
        if (empty($fields)) {
            $fields = ['*'];
        }
        return $model->_first($where, $parameters, $fields, $order);
    }

    /**
     * @param string $where
     * @param array $parameters
     * @param string[] $fields
     * @param array $orderList
     * @return $this|null
     */
    protected function _first(
        $where = '',
        $parameters = [],
        $fields = ["*"],
        $orderList = []
    ) {
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
            $this->_loadAssociations($first);
            return $this;
        }
        return null;
    }

    public static function find($modelId)
    {
        $model = new static();
        $primaryKey = $model->_getClassMetadata()->getSingleIdentifierColumnName();
        return $model->_first("$primaryKey = :$primaryKey", [":$primaryKey" => $modelId]);
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
        [
            'primaryProperty' => $primaryProperty,
        ] = $this->_getPrimaryColumn();

        unset($this->$primaryProperty);
    }

    private function _getContainer(): Container
    {
        return Container::getContainer();
    }

    public function _getTable()
    {
        return $this->_getClassMetadata()->getTableName();
    }

    public function _getConnection(): ConnectionInterface
    {
        static $connection;

        $connectionString = 'database.connection.' . $this->_getClassMetadata()->table['connection'];
        if (empty($connection)) {
            if ($this->_getContainer()->has($connectionString)) {
                $database = $this->_getContainer()->get($connectionString);
            } else {
                throw new ConnectionException("PDOConnection: {$connectionString} not found");
            }
            $connection = $database;
        }
        return $connection;
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

    final public function _getClassMetadata(): ClassMetadata
    {
        static $classMetadata;

        if (!$classMetadata) {
            // TODO: load metadata from cache
            $class = static::class;
            $classMetadata = $this->_getContainer()->make(
                ClassMetadata::class . '@' . $class,
                [
                    'class' => ClassMetadata::class,
                    'arguments' => [$class]
                ]
            );
            $this->_getContainer()
                ->get(AnnotationDriver::class)->loadMetadataForClass($class, $classMetadata);
        }
        return $classMetadata;
    }

    private function _getPrimaryColumn()
    {
        $primaryProperty = $this->_getClassMetadata()->getSingleIdentifierPropertyName();
        return [
            'primary' => $this->_getClassMetadata()->getPropertyMapping($primaryProperty),
            'primaryProperty' => $primaryProperty,
            'primaryColumn' => $this->_getClassMetadata()->getSingleIdentifierColumnName(),
        ];
    }

    private function _loadAssociations(array $initData)
    {
        $primaryProperty = $this->_getPrimaryColumn()['primaryProperty'];
        ModelStore::store(static::class, $this->$primaryProperty, $this);

        $associationMappings = $this->_getClassMetadata()->getAssociationMappings();
        foreach ($associationMappings as $property => $associationMapping) {
            /** @var string $type */
            $type = $this->_getClassMetadata()->getPropertyType($property);
            if (!is_a($type, Model::class, true)) {
                continue;
            }
            if ($associationMapping['type'] & ClassMetadata::TO_ONE) {
                $column = $this->_getClassMetadata()->getColumnForProperty($property);
                if (!array_key_exists($column, $initData)) {
                    continue;
                }
                $initDatum = $initData[$column];
                $storedInstance = ModelStore::retrieve($type, $initDatum);
                if (isset($this->$property) && ($this->$property == $storedInstance)) {
                    continue;
                }
                if (ModelStore::has($type, $initDatum)) {
                    $this->$property = $storedInstance;
                    continue;
                }
                $column = $this->_getClassMetadata()->getSingleAssociationReferencedJoinColumnName($property);

                $value = $type::first("$column = ?", [$initDatum]);
                $this->$property = $value;
            } elseif ($associationMapping['type'] == ClassMetadata::ONE_TO_MANY) {
                $mappedProperty = $this->_getClassMetadata()->getAssociationMappedByTargetProperty($property);

                $column = (new $type())->_getClassMetadata()->getColumnForProperty($mappedProperty);
                /** @var EntityCollection $many */
                $many = $type::all(
                    "$column = ?",
                    [$this->$primaryProperty],
                    ['*'],
                    $associationMapping['orderBy'] ?? [],
                    $associationMapping['limit'] ?? null
                );

                $this->$property = $many;
            }
        }
    }
}

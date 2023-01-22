<?php

namespace Terablaze\Database\ORM;

use DateTime;
use Exception;
use Terablaze\Database\Exception\ConnectionException;
use Terablaze\Database\ORM\Exception\MappingException;
use Terablaze\Encryption\Encrypter;
use Terablaze\Queue\QueueableEntity;
use Terablaze\Support\ArrayMethods;
use Terablaze\Config\PolymorphismTrait;
use Terablaze\Container\Container;
use Terablaze\Database\Connection\ConnectionInterface;
use Terablaze\Database\Query\QueryBuilderInterface;
use Terablaze\Database\ORM\Exception\PropertyException;
use Terablaze\Support\StringMethods;
use Throwable;

abstract class Model implements ModelInterface, QueueableEntity
{
    use PolymorphismTrait;

    public function _getKey()
    {
        return $this->{$this->_getKeyName()};
    }

    public function _getKeyName()
    {
        return $this->_getPrimaryColumn()['primaryProperty'];
    }

    public static function __callStatic(string $method, array $parameters = [])
    {
        return static::query()->table((new static())->_getTable())->$method(...$parameters);
    }

    /**
     * Maps associative array to object properties
     *
     * @param array<string, mixed> $initData
     */
    protected function initExternalData(array $initData): void
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
     * Maps associative array to object properties
     *
     * @param array<string, mixed> $initData
     */
    protected function initInternalData(array $initData): void
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
            try {
                if ($this->_getClassMetadata()->getPropertyMapping($prop)['encrypt'] ?? false) {
                    $value = $this->_getEncrypter()->decryptString((string)$value);
                }
            } catch (MappingException $exception) {
                // TODO: Add a logger to log the exception
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
        $propertyType = StringMethods::lower($this->_getClassMetadata()->getPropertyType($property));
        if (
            in_array($propertyType, self::DATE_TYPES, true) &&
            (!$value instanceof DateTime) &&
            (!empty($value)) &&
            ($this->_getClassMetadata()->getPropertyOptions($property)['convertDate'] ?? true != false)
        ) {
            try {
                $value = $propertyType == "timestamp" ?
                    (new DateTime())->setTimestamp($value) :
                    new DateTime($value);
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
        return $model->_getConnection()->getQueryBuilder()->table($model->_getTable());
    }

    /**
     * Creates a new Model and tries to save in the database
     *
     * @param array<int|string, mixed> $initData
     * @return static|null
     */
    public static function create(array $initData = [])
    {
        $model = new static();
        if (is_array($initData) && (!empty($initData))) {
            $model->initExternalData($initData);
        }
        if ($model->save()) {
            $model->_loadAssociations($initData);
            return $model;
        }
        return null;
    }

    /**
     * Creates a new Model without saving in the database
     * @param array<int|string, mixed> $initData
     * @return static|null
     */
    public static function hydrate(array $initData = [])
    {
        $model = new static();
        if (is_array($initData) && (!empty($initData))) {
            $model->initExternalData($initData);
        }
        $model->_loadAssociations($initData);
        return $model;
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
        $allMappings = $this->_getClassMetadata()->getAllMappings();
        foreach ($allMappings as $property => $mapping) {
            $type = $mapping['type'] ?? '';
            if (is_int($type) && ($mapping['type'] & ClassMetadata::TO_MANY)) {
                continue;
            }
            $queryName = $this->_getClassMetadata()->getColumnForProperty($property);
            if (false == ($mapping['id'] ?? false)) {
                if (!property_exists($this, $property)) {
                    continue;
                }
                $datum = $this->resolveDatum($property, $mapping);
                if (
                    is_null($datum) &&
                    (($mapping['nullable'] ?? $mapping['joinColumns'][0]['nullable'] ?? true) == false)
                ) {
                    continue;
                }
                if ($mapping['encrypt'] ?? false) {
                    $datum = $this->_getEncrypter()->encryptString((string) $datum);
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
        $datum = $this->getDatum($prop, $column);
        if ($datum instanceof DateTime && ($column['options']['convertDate'] ?? true != false)) {
            if (StringMethods::lower($this->_getClassMetadata()->getPropertyType($prop)) == 'timestamp') {
                $datum = $datum->getTimestamp();
            } else {
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

    /**
     * @param string $prop
     * @param array $column
     * @return mixed|null
     */
    private function getDatum(string $prop, array $column)
    {
        $columnType = StringMethods::lower($column['type']);
        $default = $column['options']['default'] ?? null;
        if (
            in_array($columnType, self::DATE_TYPES) &&
            in_array(StringMethods::lower($default ?? ''), ['now', 'now()', 'current_timestamp'])
        ) {
            return $this->$prop ?? new DateTime();
        }
        return $this->$prop ?? $default ?? null;
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
                ->delete("`{$this->_getTable()}`")
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
    ): EntityCollection {
        $model = new static();
        if (empty($fields)) {
            $fields = ['*'];
        }
        return $model->_all($where, $parameters, $fields, $order, $limit, $offset, $page);
    }

    protected function _all(
        $where,
        $parameters = [],
        $fields = ["*"],
        $orderList = [],
        $limit = null,
        $offset = null,
        $page = null
    ) {
        $fields = ArrayMethods::wrap($fields);
        $query = $this
            ->_getConnection()
            ->getQueryBuilder()
            ->select(...$fields)
            ->from("`{$this->_getTable()}`");
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
            $object->initInternalData($row);
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
     * @return static|null
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
     * @return static|null
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
            ->from("`{$this->_getTable()}`");
        $this->buildWhereQuery($where, $parameters, $query);
        $this->buildOrderQuery($orderList, $query);
        $first = $query->first();
        if ($first) {
            $this->initInternalData($first);
            $this->_loadAssociations($first);
            return $this;
        }
        return null;
    }

    /**
     * @param int|string $modelId
     * @return static|null
     * @throws MappingException
     */
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

    /**
     * Get the queueable identity for the entity.
     *
     * @return mixed
     */
    public function getQueueableId()
    {
        return $this->getKey();
    }

    /**
     * Get the queueable connection for the entity.
     *
     * @return string|null
     */
    public function getQueueableConnection()
    {
        return $this->_getConnection()->getName();
    }

    protected function _count($where = '', $parameters = [])
    {
        $query = $this
            ->_getConnection()
            ->getQueryBuilder()
            ->from("`{$this->_getTable()}`");
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

    private function _getEncrypter(): Encrypter
    {
        return $this->_getContainer()->get(Encrypter::class);
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
        // TODO: load metadata from cache
        $class = static::class;
        if ($this->_getContainer()->has(ClassMetadata::class . '@' . $class)) {
            return $this->_getContainer()->get(ClassMetadata::class . '@' . $class);
        }
        $classMetadata = $this->_getContainer()->make(
            ClassMetadata::class . '@' . $class,
            [
                'class' => ClassMetadata::class,
                'arguments' => [$class]
            ]
        );
        $this->_getContainer()
            ->get(AnnotationDriver::class)->loadMetadataForClass($class, $classMetadata);
        return $classMetadata;
    }

    public function _getPrimaryColumn()
    {
        $primaryProperty = $this->_getClassMetadata()->getSingleIdentifierPropertyName();
        return [
            'primary' => $this->_getClassMetadata()->getPropertyMapping($primaryProperty),
            'primaryProperty' => $primaryProperty,
            'primaryColumn' => $this->_getClassMetadata()->getSingleIdentifierColumnName(),
        ];
    }

    /**
     * Determine if two models have the same ID and belong to the same table.
     *
     * @param  Model|null  $model
     * @return bool
     */
    public function _is($model)
    {
        return ! is_null($model) &&
            $this->_getKey() === $model->_getKey() &&
            $this->_getTable() === $model->_getTable() &&
            $this->_getConnection() === $model->_getConnection();
    }

    /**
     * Determine if two models are not the same.
     *
     * @param  Model|null  $model
     * @return bool
     */
    public function _isNot($model)
    {
        return ! $this->_is($model);
    }

    private function _loadAssociations(array $initData)
    {
        ModelStore::store(static::class, $this->_getKey(), $this);

        $associationMappings = $this->_getClassMetadata()->getAssociationMappings();
        foreach ($associationMappings as $property => $associationMapping) {
            /** @var string|static $type */
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
                if (isset($this->$property) && ($this->$property === $storedInstance)) {
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

                $typeInstance = (new $type());
                $column = $typeInstance->_getClassMetadata()->getColumnForProperty($mappedProperty);

                $inverseColumn = $typeInstance->_getClassMetadata()
                    ->getSingleAssociationReferencedJoinColumnName($mappedProperty);
                $inverseProperty = $this->_getClassMetadata()->getPropertyForColumn($inverseColumn);
                $annotWhere = $associationMapping['where'] ?? '';
                $where = "$column = ?";
                if (!empty($annotWhere)) {
                    $where .= " AND ($annotWhere)";
                }
                $pagination = $associationMapping['pagination'] ?? [];
                $many = $type::all(
                    $where,
                    [$this->$inverseProperty],
                    ['*'],
                    $associationMapping['orderBy'] ?? [],
                    $pagination['limit'] ?? $associationMapping['limit'] ?? null,
                    null,
                    $pagination['query'] ?? null
                );

                $this->$property = $many;
            } elseif ($associationMapping['type'] == ClassMetadata::MANY_TO_MANY) {
                $joinTable = $associationMapping['joinTable'];
                $joinTableName = $joinTable['name'];
                $joinTableJoinColumns = $joinTable['joinColumns'];
                $joinTableInverseColumns = $joinTable['inverseJoinColumns'];

                $typeInstance = (new $type());
                $associationTableName = $typeInstance->_getTable();
                $resultsQuery = $this->_getConnection()->getQueryBuilder()->select(["pt.*"])
                    ->table("`$associationTableName`", 'pt');
                $inverseTableConditions = [];
                foreach ($joinTableInverseColumns as $joinTableInverseColumn) {
                    $inverseTableConditions[] =
                        "pt.{$joinTableInverseColumn['referencedColumnName']} = st.{$joinTableInverseColumn['name']}";
                }
                $resultsQuery->join(
                    'pt',
                    "`$joinTableName`",
                    "st",
                    "(" . implode(" AND ", $inverseTableConditions) . ")"
                );

                $tableConditions = [];
                foreach ($joinTableJoinColumns as $joinTableJoinColumn) {
                    $tableConditions[] =
                        "ct.{$joinTableJoinColumn['referencedColumnName']} = st.{$joinTableJoinColumn['name']}";
                }
                $resultsQuery->join(
                    'st',
                    "`{$this->_getTable()}`",
                    "ct",
                    "(" . implode(" AND ", $tableConditions) . ")"
                );

                $annotWhere = $associationMapping['where'] ?? '';
                $column = $this->_getClassMetadata()->getSingleIdentifierColumnName();
                $where = "ct.$column = ?";
                if (!empty($annotWhere)) {
                    $where .= " AND ($annotWhere)";
                }

                $resultsQuery
                    ->where($where)
                    ->setParameters([$this->{$this->_getClassMetadata()->getSingleIdentifierPropertyName()}]);
                $this->buildOrderQuery($associationMapping['orderBy'] ?? [], $resultsQuery);

                if ($pagination = $associationMapping['pagination'] ?? []) {
                    if ($pagination['type'] == 'page') {
                        $resultsQuery->limitByPage($pagination['limit'], $pagination['query']);
                    }
                } else {
                    $this->buildLimitQuery($associationMapping['limit'] ?? null, null, null, $resultsQuery);
                }

                $rows = $resultsQuery->all();

                $many = [];
                foreach ($rows as $row) {
                    $object = new $type();
                    $object->initInternalData($row);
                    $many[] = $object;
                    $object = null;
                }

                $this->$property = new EntityCollection($many, $type);
            }
        }
    }
}

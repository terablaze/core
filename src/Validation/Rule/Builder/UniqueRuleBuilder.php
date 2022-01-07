<?php

namespace TeraBlaze\Validation\Rule\Builder;

use TeraBlaze\Database\ORM\Model;
use TeraBlaze\Database\ORM\ModelInterface;

class UniqueRuleBuilder implements RuleBuilderInterface
{
    protected string $table;

    /**
     * The ID that should be ignored.
     *
     * @var mixed
     */
    protected $ignore;

    /**
     * The name of the ID column.
     *
     * @var string
     */
    protected $idColumn = 'id';

    protected $column = 'NULL';

    public function __construct(string $table, string $column = "NULL")
    {
        $this->table = $table;
        $this->column = $column;
    }

    /**
     * Ignore the given ID during the unique check.
     *
     * @param  mixed  $id
     * @param  string|null  $idColumn
     * @return $this
     */
    public function ignore($id, $idColumn = null)
    {
        if ($id instanceof Model) {
            return $this->ignoreModel($id, $idColumn);
        }

        $this->ignore = $id;
        $this->idColumn = $idColumn ?? 'id';

        return $this;
    }

    /**
     * Ignore the given model during the unique check.
     *
     * @param  Model  $model
     * @param  string|null  $idColumn
     * @return $this
     */
    public function ignoreModel(ModelInterface $model, $idColumn = null)
    {
        $this->idColumn = $idColumn ?? $model->_getClassMetadata()->getSingleIdentifierColumnName();
        $this->ignore = $model->_getClassMetadata()
            ->getReflectionClass()->getProperty($this->idColumn)
            ->getValue($model);

        return $this;
    }

    public function __toString()
    {
        return rtrim(sprintf('unique:%s,%s,%s,%s',
            $this->table,
            $this->column,
            $this->ignore ?? 'NULL',
            $this->idColumn,
        ), ',');
    }
}

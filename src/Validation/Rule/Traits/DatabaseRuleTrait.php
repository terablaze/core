<?php

namespace Terablaze\Validation\Rule\Traits;

use Terablaze\Container\Container;
use Terablaze\Database\Connection\ConnectionInterface;
use Terablaze\Database\ORM\ClassMetadata;
use Terablaze\Database\ORM\Model;
use Terablaze\Database\Query\QueryBuilderInterface;
use Terablaze\Support\StringMethods;
use Terablaze\Validation\Exception\RuleException;

trait DatabaseRuleTrait
{
    protected string $connectionName = "default";

    /** @var ConnectionInterface $connection */
    protected $connection;

    /** @var QueryBuilderInterface $queryBuilder */
    protected $queryBuilder;

    /**
     * The table to run the query against.
     *
     * @var string
     */
    protected $table;

    /**
     * The column to check on.
     *
     * @var string
     */
    protected $column;

    public function setDatabaseReqs(Container $container): void
    {
        if (empty($this->params[0])) {
            throw new RuleException('Database validation rule must have either a table name or a model name');
        }
        $this->column = (empty($this->params[1]) || $this->params[1] == "NULL") ? $this->field : $this->params[1];
        if (is_subclass_of($this->params[0], Model::class)) {
            /** @var Model $model */
            $model = (new ClassMetadata($this->params[0]))->newInstance();
            $classMetadata = $model->_getClassMetadata();
            $this->table = $classMetadata->table['name'];
            $this->connectionName = $classMetadata->table['connection'];
            $this->resolveConnection($container);
            return;
        }
        if (StringMethods::contains($this->params[0], '.')) {
            [$this->connectionName, $this->table] = explode('.', $this->params[0], 2);
        } else {
            $this->table = $this->params[0];
        }
        $this->resolveConnection($container);
    }

    public function resolveConnection(Container $container): void
    {
        if ($container->has('database.connection.' . $this->connectionName)) {
            $this->connection = $container->get('database.connection.' . $this->connectionName);
            $this->queryBuilder = $this->connection->getQueryBuilder();
        }
    }
}

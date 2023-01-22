<?php

namespace Terablaze\Database\Migrations;

use Terablaze\Collection\ArrayCollection;
use Terablaze\Database\Connection\ConnectionInterface;
use Terablaze\Database\Query\QueryBuilderInterface;

class MigrationRepository
{
    /**
     * The name of the migration table.
     *
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * The name of the database connection to use.
     *
     * @var string
     */
    protected string $table;

    /**
     * Create a new database migration repository instance.
     *
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->table = $connection->getMigrationsTable();
    }

    /**
     * Get the completed migrations.
     *
     * @return string[]
     */
    public function getRan(): array
    {
        $allRan = $this->query()
            ->from($this->table)
            ->select('name')
            ->orderBy('batch', 'asc')
            ->addOrderBy('name', 'asc')
            ->all();
        return (new ArrayCollection($allRan))->pluck('name')->all();
    }

    /**
     * Get list of migrations.
     *
     * @param int $steps
     * @return array<int|string, mixed>
     */
    public function getMigrations(int $steps): array
    {
        $query = $this->table()->where('batch >= 1');

        return $query->orderBy('batch', 'desc')
            ->addOrderBy('name', 'desc')
            ->limit($steps)->all();
    }

    /**
     * Get the last migration batch.
     *
     * @return array
     */
    public function getLast(): array
    {
        $query = $this->table()->where('batch = :lastBatchNumber')
            ->setParameter('lastBatchNumber', $this->getLastBatchNumber());

        return $query->orderBy('name', 'desc')->all();
    }

    /**
     * Get the completed migrations with their batch numbers.
     *
     * @return array
     */
    public function getMigrationBatches(): array
    {
        return $this->table()
            ->select('batch', 'name')
            ->orderBy('batch', 'asc')
            ->addOrderBy('name', 'asc')
            ->all();
    }

    /**
     * Log that a migration was run.
     *
     * @param string $file
     * @param int $batch
     * @return void
     */
    public function log(string $file, int $batch)
    {
        $record = ['name' => ":name", 'batch' => ":batch"];
        $params = ['name' => $file, 'batch' => $batch];

        $this->table()->save($record, $params);
    }

    /**
     * Remove a migration from the log.
     *
     * @param mixed $migration
     * @return void
     */
    public function delete($migration)
    {
        $this->query()->delete($this->table)->where('name = :name')
            ->setParameter('name', $migration->name)->execute();
    }

    /**
     * Get the next migration batch number.
     *
     * @return int
     */
    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get the last migration batch number.
     *
     * @return null|int
     */
    public function getLastBatchNumber(): ?int
    {
        return $this->table()->max('batch');
    }

    /**
     * Create the migration repository data store.
     *
     * @return void
     */
    public function createRepository()
    {
        $table = $this->connection->createTable($this->connection->getMigrationsTable());
        $table->id('id');
        $table->string('name');
        $table->int('batch');
        $table->build();
    }

    /**
     * Determine if the migration repository exists.
     *
     * @return bool
     */
    public function repositoryExists(): bool
    {
        return $this->connection->hasTable($this->table);
    }

    /**
     * Delete the migration repository data store.
     *
     * @return void
     */
    public function deleteRepository()
    {
        $this->connection->dropTableIfExists($this->table)->build();
    }

    /**
     * Get a query builder for the migration table.
     *
     * @return QueryBuilderInterface
     */
    protected function table(): QueryBuilderInterface
    {
        return $this->query()->table($this->table);
    }

    protected function query(): QueryBuilderInterface
    {
        return $this->connection->getQueryBuilder();
    }

    public function setConnection(ConnectionInterface $connection): void
    {
        $this->connection = $connection;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }
}

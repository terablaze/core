<?php

namespace TeraBlaze\Database\Migrations;

use TeraBlaze\Database\Connection\ConnectionInterface;
use TeraBlaze\Database\Query\QueryBuilderInterface;

class MigrationRepository
{
    /**
     * The name of the migration table.
     *
     * @var string
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
     * @return array
     */
    public function getRan(): array
    {
        return $this->query()
            ->select('migration')
            ->orderBy('batch', 'asc')
            ->addOrderBy('migration', 'asc')
            ->all();
    }

    /**
     * Get list of migrations.
     *
     * @param int $steps
     * @return array
     */
    public function getMigrations(int $steps): array
    {
        $query = $this->table()->where('batch >= 1');

        return $query->orderBy('batch', 'desc')
            ->addOrderBy('migration', 'desc')
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

        return $query->orderBy('migration', 'desc')->all();
    }

    /**
     * Get the completed migrations with their batch numbers.
     *
     * @return array
     */
    public function getMigrationBatches(): array
    {
        return $this->table()
            ->select('batch', 'migration')
            ->orderBy('batch', 'asc')
            ->addOrderBy('migration', 'asc')
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
        $record = ['migration' => $file, 'batch' => $batch];

        $this->table()->save($record);
    }

    /**
     * Remove a migration from the log.
     *
     * @param $migration
     * @return void
     */
    public function delete($migration)
    {
        $this->query()->delete($this->table)->where('migration = :migration')
            ->setParameter('migration', $migration['migration'])->execute();
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
     * @return int
     */
    public function getLastBatchNumber(): int
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
        $table->execute();
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
        $this->connection->dropTableIfExists($this->table)->execute();
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
}

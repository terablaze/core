<?php

namespace Terablaze\Queue\Failed;

use Carbon\Carbon;
use DateTimeInterface;
use Terablaze\Database\Connection\ConnectionInterface as DatabaseConnection;
use Terablaze\Database\Query\QueryBuilderInterface;

class DatabaseUuidFailedJobProvider implements FailedJobProviderInterface, PrunableFailedJobProvider
{
    /**
     * The database connection
     *
     * @var DatabaseConnection
     */
    protected $connection;

    /**
     * The database connection name.
     *
     * @var string
     */
    protected $database;

    /**
     * The database table.
     *
     * @var string
     */
    protected $table;

    /**
     * Create a new database failed job provider.
     *
     * @param DatabaseConnection $resolver
     * @param string $database
     * @param string $table
     * @return void
     */
    public function __construct(DatabaseConnection $connection, $database, $table)
    {
        $this->table = $table;
        $this->connection = $connection;
        $this->database = $database;
    }

    /**
     * Log a failed job into storage.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @param  \Throwable  $exception
     * @return string|null
     */
    public function log($connection, $queue, $payload, $exception)
    {
        $this->getTable()->insert()->values([
            'uuid' => $uuid = json_decode($payload, true)['uuid'],
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'exception' => (string) mb_convert_encoding($exception, 'UTF-8'),
            'failed_at' => Carbon::now(),
        ]);

        return $uuid;
    }

    /**
     * Get a list of all of the failed jobs.
     *
     * @return array
     */
    public function all()
    {
        return $this->getTable()->orderBy('id', 'desc')->get()->map(function ($record) {
            $record->id = $record->uuid;
            unset($record->uuid);

            return $record;
        })->all();
    }

    /**
     * Get a single failed job.
     *
     * @param  mixed  $id
     * @return object|null
     */
    public function find($id)
    {
        if ($record = (object)$this->getTable()->where('uuid = :id')->setParameter('id', $id)->first()) {
            $record->id = $record->uuid;
            unset($record->uuid);
        }

        return $record;
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param  mixed  $id
     * @return bool
     */
    public function forget($id)
    {
        return $this->connection->query()->delete($this->table)->where('uuid = :id')->setParameter('id', $id)->execute() > 0;
    }

    /**
     * Flush all of the failed jobs from storage.
     *
     * @param  int|null  $hours
     * @return void
     */
    public function flush($hours = null)
    {
        $this->connection->query()->delete($this->table)->when($hours, function (QueryBuilderInterface $query, $hours) {
            $query->where('failed_at <= :failedTime')
                ->setParameter('failedTime', Carbon::now()->subHours($hours));
        })->execute();
    }

    /**
     * Prune all of the entries older than the given date.
     *
     * @param  \DateTimeInterface  $before
     * @return int
     */
    public function prune(DateTimeInterface $before)
    {
        $query = $this->connection->query()->delete($this->table)->where('failed_at < :before')->setParameter('before', $before);

        $totalDeleted = 0;

        do {
            $deleted = $query->limit(1000)->execute();

            $totalDeleted += $deleted;
        } while ($deleted !== 0);

        return $totalDeleted;
    }

    /**
     * Get a new query builder instance for the table.
     *
     * @return QueryBuilderInterface
     */
    protected function getTable()
    {
        return $this->connection->query()->table($this->table);
    }
}

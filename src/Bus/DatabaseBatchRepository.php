<?php

namespace Terablaze\Bus;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Terablaze\Database\Query\QueryBuilderInterface;
use Terablaze\Support\Helpers;
use Terablaze\Support\StringMethods;
use Terablaze\Database\Connection\ConnectionInterface as DatabaseConnection;

class DatabaseBatchRepository implements PrunableBatchRepositoryInterface
{
    /**
     * The batch factory instance.
     *
     * @var \Terablaze\Bus\BatchFactory
     */
    protected $factory;

    /**
     * The database connection instance.
     *
     * @var DatabaseConnection
     */
    protected $connection;

    /**
     * The database table to use to store batch information.
     *
     * @var string
     */
    protected $table;

    /**
     * Create a new batch repository instance.
     *
     * @param \Terablaze\Bus\BatchFactory $factory
     * @param DatabaseConnection $connection
     * @param string $table
     */
    public function __construct(BatchFactory $factory, DatabaseConnection $connection, $table)
    {
        $this->factory = $factory;
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * Retrieve a list of batches.
     *
     * @param int $limit
     * @param mixed $before
     * @return \Terablaze\Bus\Batch[]
     */
    public function get($limit = 50, $before = null)
    {
        return $this->connection->query()->table($this->table)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->when(
                $before,
                fn(QueryBuilderInterface $q) => $q
                    ->where('id < :before')
                    ->setParameter("before", $before)
            )
            ->get()
            ->map(function ($batch) {
                return $this->toBatch($batch);
            })
            ->all();
    }

    /**
     * Retrieve information about an existing batch.
     *
     * @param string $batchId
     * @return \Terablaze\Bus\Batch|null
     */
    public function find(string $batchId)
    {
        $batch = $this->connection->query()->select('*')->from($this->table)
            ->where('id = :batchId')
            ->setParameter(':batchId', $batchId)
            ->first();

        if ($batch) {
            return $this->toBatch((object)$batch);
        }
        return null;
    }

    /**
     * Store a new pending batch.
     *
     * @param \Terablaze\Bus\PendingBatch $batch
     * @return \Terablaze\Bus\Batch
     */
    public function store(PendingBatch $batch)
    {
        $id = (string)StringMethods::orderedUuid();

        $this->connection->query()->table($this->table)->save([
            'id' => $id,
            'name' => $batch->name,
            'total_jobs' => 0,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => $this->serialize($batch->options),
            'created_at' => time(),
            'cancelled_at' => null,
            'finished_at' => null,
        ]);

        return $this->find($id);
    }

    /**
     * Increment the total number of jobs within the batch.
     *
     * @param string $batchId
     * @param int $amount
     * @return void
     */
    public function incrementTotalJobs(string $batchId, int $amount)
    {
        $this->connection->query()->update($this->table)->where('id = :batchId')->save([
            'total_jobs' => 'total_jobs + ' . $amount,
            'pending_jobs' => 'pending_jobs + ' . $amount,
            'finished_at' => null,
        ], ["batchId" => $batchId]);
    }

    /**
     * Decrement the total number of pending jobs for the batch.
     *
     * @param string $batchId
     * @param string $jobId
     * @return \Terablaze\Bus\UpdatedBatchJobCounts
     */
    public function decrementPendingJobs(string $batchId, string $jobId)
    {
        $values = $this->updateAtomicValues($batchId, function ($batch) use ($jobId) {
            return [
                'pending_jobs' => $batch->pending_jobs - 1,
                'failed_jobs' => $batch->failed_jobs,
                'failed_job_ids' => json_encode(
                    array_values(array_diff(json_decode($batch->failed_job_ids, true), [$jobId]))
                ),
            ];
        });

        return new UpdatedBatchJobCounts(
            $values['pending_jobs'],
            $values['failed_jobs']
        );
    }

    /**
     * Increment the total number of failed jobs for the batch.
     *
     * @param string $batchId
     * @param string $jobId
     * @return \Terablaze\Bus\UpdatedBatchJobCounts
     */
    public function incrementFailedJobs(string $batchId, string $jobId)
    {
        $values = $this->updateAtomicValues($batchId, function ($batch) use ($jobId) {
            return [
                'pending_jobs' => $batch->pending_jobs,
                'failed_jobs' => $batch->failed_jobs + 1,
                'failed_job_ids' => json_encode(
                    array_values(array_unique(array_merge(json_decode($batch->failed_job_ids, true), [$jobId])))
                ),
            ];
        });

        return new UpdatedBatchJobCounts(
            $values['pending_jobs'],
            $values['failed_jobs']
        );
    }

    /**
     * Update an atomic value within the batch.
     *
     * @param string $batchId
     * @param \Closure $callback
     * @return int|null
     */
    protected function updateAtomicValues(string $batchId, Closure $callback)
    {
        return $this->connection->transaction(function () use ($batchId, $callback) {
            $batch = $this->connection->query()->select('*')
                ->from($this->table)
                ->where('id = :batchId')
                ->setParameter('batchId', $batchId)
                ->lockForUpdate()
                ->first();

            return is_null($batch) ? [] : Helpers::tap($callback($batch), function ($values) use ($batchId) {
                $this->connection->query()->update($this->table)
                    ->where('id = :batchId')
                    ->setParameter('batchId', $batchId)
                    ->save($values);
            });
        });
    }

    /**
     * Mark the batch that has the given ID as finished.
     *
     * @param string $batchId
     * @return void
     */
    public function markAsFinished(string $batchId)
    {
        $this->connection->query()->update($this->table)
            ->where('id = :batchId')
            ->setParameter('batchId', $batchId)
            ->save(['finished_at' => time(),]);
    }

    /**
     * Cancel the batch that has the given ID.
     *
     * @param string $batchId
     * @return void
     */
    public function cancel(string $batchId)
    {
        $this->connection->query()->update($this->table)
            ->where('id = :batchId')
            ->setParameter('batchId', $batchId)
            ->save([
                'cancelled_at' => time(),
                'finished_at' => time(),
            ]);
    }

    /**
     * Delete the batch that has the given ID.
     *
     * @param string $batchId
     * @return void
     */
    public function delete(string $batchId)
    {
        $this->connection->query()->delete($this->table)
            ->where('id = :batchId')
            ->setParameter('batchId', $batchId)
            ->execute();
    }

    /**
     * Prune all of the entries older than the given date.
     *
     * @param \DateTimeInterface $before
     * @return int
     */
    public function prune(DateTimeInterface $before)
    {
        $query = $this->connection->query()->table($this->table)
            ->where('finished_at IS NOT NULL')
            ->andWhere('finished_at < :beforeTimestamp')
            ->setParameter('beforeTimestamp', $before->getTimestamp());

        $totalDeleted = 0;

        do {
            $deleted = $query->limit(1000)->delete()->execute();

            $totalDeleted += $deleted;
        } while ($deleted !== 0);

        return $totalDeleted;
    }

    /**
     * Prune all the unfinished entries older than the given date.
     *
     * @param \DateTimeInterface $before
     * @return int
     */
    public function pruneUnfinished(DateTimeInterface $before)
    {
        $query = $this->connection->query()->table($this->table)
            ->where('finished_at IS NULL')
            ->andWhere('created_at < :beforeTimestamp')
            ->setParameter('beforeTimestamp', $before->getTimestamp());

        $totalDeleted = 0;

        do {
            $deleted = $query->limit(1000)->delete()->execute();

            $totalDeleted += $deleted;
        } while ($deleted !== 0);

        return $totalDeleted;
    }

    /**
     * Prune all of the cancelled entries older than the given date.
     *
     * @param \DateTimeInterface $before
     * @return int
     */
    public function pruneCancelled(DateTimeInterface $before)
    {
        $query = $this->connection->query()->table($this->table)
            ->where('cancelled_at IS NOT NULL')
            ->andWhere('created_at < :beforeTimestamp')
            ->setParameter('beforeTimestamp', $before->getTimestamp());

        $totalDeleted = 0;

        do {
            $deleted = $query->limit(1000)->delete();

            $totalDeleted += $deleted;
        } while ($deleted !== 0);

        return $totalDeleted;
    }

    /**
     * Execute the given Closure within a storage specific transaction.
     *
     * @param \Closure $callback
     * @return mixed
     */
    public function transaction(Closure $callback)
    {
        return $this->connection->transaction(fn() => $callback());
    }

    /**
     * Serialize the given value.
     *
     * @param mixed $value
     * @return string
     */
    protected function serialize($value)
    {
        $serialized = serialize($value);

        // TODO: base64_encode($serialized) for Postgres
        return $serialized;
    }

    /**
     * Unserialize the given value.
     *
     * @param string $serialized
     * @return mixed
     */
    protected function unserialize($serialized)
    {
        // TODO: base64_decode($serialized) for Postgres
//        if ($this->connection instanceof PostgresConnection &&
//            !StringMethods::contains($serialized, [':', ';'])) {
//            $serialized = base64_decode($serialized);
//        }

        return unserialize($serialized);
    }

    /**
     * Convert the given raw batch to a Batch object.
     *
     * @param object $batch
     * @return \Terablaze\Bus\Batch
     */
    protected function toBatch($batch)
    {
        return $this->factory->make(
            $this,
            $batch->id,
            $batch->name,
            (int)$batch->total_jobs,
            (int)$batch->pending_jobs,
            (int)$batch->failed_jobs,
            json_decode($batch->failed_job_ids, true),
            $this->unserialize($batch->options),
            CarbonImmutable::createFromTimestamp($batch->created_at),
            $batch->cancelled_at ? CarbonImmutable::createFromTimestamp($batch->cancelled_at) : $batch->cancelled_at,
            $batch->finished_at ? CarbonImmutable::createFromTimestamp($batch->finished_at) : $batch->finished_at
        );
    }

    /**
     * Get the underlying database connection.
     *
     * @return DatabaseConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Set the underlying database connection.
     *
     * @param DatabaseConnection $connection
     * @return void
     */
    public function setConnection(DatabaseConnection $connection)
    {
        $this->connection = $connection;
    }
}

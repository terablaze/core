<?php

namespace Terablaze\Queue;

use Carbon\Carbon;
use PDO;
use Terablaze\Database\Connection\ConnectionInterface as DatabaseConnection;
use Terablaze\Database\Query\Expression\CompositeExpression;
use Terablaze\Database\Query\Expression\ExpressionBuilder;
use Terablaze\Database\Query\QueryBuilderInterface;
use Terablaze\Queue\Jobs\DatabaseJob;
use Terablaze\Queue\Jobs\DatabaseJobRecord;
use Terablaze\Queue\Jobs\JobInterface;
use Terablaze\Support\StringMethods;

class DatabaseQueue extends Queue implements QueueInterface, ClearableQueueInterface
{
    /**
     * The database connection instance.
     *
     * @var DatabaseConnection
     */
    protected $database;

    /**
     * The database table that holds the jobs.
     *
     * @var string
     */
    protected $table;

    /**
     * The name of the default queue.
     *
     * @var string
     */
    protected $default;

    /**
     * The expiration time of a job.
     *
     * @var int|null
     */
    protected $retryAfter = 60;

    /**
     * Create a new database queue instance.
     *
     * @param DatabaseConnection $database
     * @param string $table
     * @param string $default
     * @param int $retryAfter
     * @param bool $dispatchAfterCommit
     * @return void
     */
    public function __construct(
        DatabaseConnection $database,
                           $table,
                           $default = 'default',
                           $retryAfter = 60,
                           $dispatchAfterCommit = false
    )
    {
        $this->table = $table;
        $this->default = $default;
        $this->database = $database;
        $this->retryAfter = $retryAfter;
        $this->dispatchAfterCommit = $dispatchAfterCommit;
    }

    /**
     * Get the size of the queue.
     *
     * @param string|null $queue
     * @return int
     */
    public function size($queue = null): int
    {
        return $this->database->query()->from($this->table)
            ->where('queue = :queue')
            ->setParameter('queue', $this->getQueue($queue))
            ->count();
    }

    /**
     * Push a new job onto the queue.
     *
     * @param string $job
     * @param mixed $data
     * @param string|null $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            function ($payload, $queue) {
                return $this->pushToDatabase($queue, $payload);
            }
        );
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param string $payload
     * @param string|null $queue
     * @param array $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->pushToDatabase($queue, $payload);
    }

    /**
     * Push a new job onto the queue after (n) seconds.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param string $job
     * @param mixed $data
     * @param string|null $queue
     * @return void
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            $delay,
            function ($payload, $queue, $delay) {
                return $this->pushToDatabase($queue, $payload, $delay);
            }
        );
    }

    /**
     * Push an array of jobs onto the queue.
     *
     * @param array $jobs
     * @param mixed $data
     * @param string|null $queue
     * @return mixed
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        $queue = $this->getQueue($queue);

        $now = $this->availableAt();

        return $this->database->query()->table($this->table)->save(...(collect((array)$jobs)->map(
            function ($job) use ($queue, $data, $now) {
                return $this->buildDatabaseRecord(
                    $queue,
                    $this->createPayload($job, $this->getQueue($queue), $data),
                    isset($job->delay) ? $this->availableAt($job->delay) : $now,
                );
            }
        )->all()));
    }

    /**
     * Release a reserved job back onto the queue after (n) seconds.
     *
     * @param string $queue
     * @param DatabaseJobRecord $job
     * @param int $delay
     * @return mixed
     */
    public function release($queue, $job, $delay)
    {
        return $this->pushToDatabase($queue, $job->payload, $delay, $job->attempts);
    }

    /**
     * Push a raw payload to the database with a given delay of (n) seconds.
     *
     * @param string|null $queue
     * @param string $payload
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param int $attempts
     * @return mixed
     */
    protected function pushToDatabase($queue, $payload, $delay = 0, $attempts = 0)
    {
        return $this->database->query()->insert($this->table)->saveGetId(...$this->buildDatabaseRecord(
            $this->getQueue($queue),
            $payload,
            $this->availableAt($delay),
            $attempts
        ));
    }

    /**
     * Create an array to insert for the given job.
     *
     * @param string|null $queue
     * @param string $payload
     * @param int $availableAt
     * @param int $attempts
     * @return array
     */
    protected function buildDatabaseRecord($queue, $payload, $availableAt, $attempts = 0)
    {
        return [
            [
                'queue' => ':queue',
                'attempts' => ':attempts',
                'reserved_at' => ':reserved_at',
                'available_at' => ':available_at',
                'created_at' => ':created_at',
                'payload' => ':payload',
            ],
            [
                'queue' => $queue,
                'attempts' => $attempts,
                'reserved_at' => NULL,
                'available_at' => $availableAt,
                'created_at' => $this->currentTime(),
                'payload' => $payload,
            ],
        ];
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param string|null $queue
     * @return JobInterface|null
     *
     * @throws \Throwable
     */
    public function pop($queue = null): ?JobInterface
    {
        $queue = $this->getQueue($queue);

        return $this->database->transaction(function () use ($queue) {
            if ($job = $this->getNextAvailableJob($queue)) {
                return $this->marshalJob($queue, $job);
            }
            return null;
        });
    }

    /**
     * Get the next available job for the queue.
     *
     * @param string|null $queue
     * @return DatabaseJobRecord|null
     */
    protected function getNextAvailableJob($queue)
    {
        $query = $this->database->query();
        $query = $query->select('*')->from($this->table)
            ->lock($this->getLockForPopping())
            ->where('queue = :queue');
        $query->andWhere($query->expr()->or(
            $this->isAvailable($query),
            $this->isReservedButExpired($query))
        );
        $job = $query->setParameter(':queue', $this->getQueue($queue))
            ->orderBy('id', 'asc')
            ->first();

        return $job ? new DatabaseJobRecord((object)$job) : null;
    }

    /**
     * Get the lock required for popping the next job.
     *
     * @return string|bool
     */
    protected function getLockForPopping()
    {
        $databaseEngine = $this->database->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $databaseVersion = $this->database->getConfig('version')
            ?? $this->database->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

        if (StringMethods::of($databaseVersion)->contains('MariaDB')) {
            $databaseEngine = 'mariadb';
            $databaseVersion = StringMethods::before(StringMethods::after($databaseVersion, '5.5.5-'), '-');
        } elseif (StringMethods::of($databaseVersion)->contains(['vitess', 'PlanetScale'])) {
            $databaseEngine = 'vitess';
            $databaseVersion = StringMethods::before($databaseVersion, '-');
        }

        if (
            ($databaseEngine === 'mysql' && version_compare($databaseVersion, '8.0.1', '>=')) ||
            ($databaseEngine === 'mariadb' && version_compare($databaseVersion, '10.6.0', '>=')) ||
            ($databaseEngine === 'pgsql' && version_compare($databaseVersion, '9.5', '>='))
        ) {
            return 'FOR UPDATE SKIP LOCKED';
        }

        if ($databaseEngine === 'sqlsrv') {
            return 'with(rowlock,updlock,readpast)';
        }

        return true;
    }

    /**
     * Modify the query to check for available jobs.
     *
     * @param QueryBuilderInterface $query
     * @return CompositeExpression
     */
    protected function isAvailable($query)
    {
        $expr = $query->expr();

        $query->setParameter('current_time', $this->currentTime());
        return $expr->and(
            $expr->isNull('reserved_at'),
            $expr->lte('available_at', ':current_time')
        );
    }

    /**
     * Modify the query to check for jobs that are reserved but have expired.
     *
     * @param QueryBuilderInterface $query
     * @return string
     */
    protected function isReservedButExpired($query)
    {
        $expiration = Carbon::now()->subSeconds($this->retryAfter)->getTimestamp();

        $expr = $query->expr();
        $query->setParameter('expiration', $expiration);

        return $expr->lte('reserved_at', ':expiration');
    }

    /**
     * Marshal the reserved job into a DatabaseJob instance.
     *
     * @param string $queue
     * @param DatabaseJobRecord|\stdClass $job
     * @return DatabaseJob
     */
    protected function marshalJob($queue, $job)
    {
        $job = $this->markJobAsReserved($job);

        return new DatabaseJob(
            $this->container,
            $this,
            $job,
            $this->connectionName,
            $queue
        );
    }

    /**
     * Mark the given job ID as reserved.
     *
     * @param DatabaseJobRecord $job
     * @return DatabaseJobRecord
     */
    protected function markJobAsReserved($job)
    {
        $record = ['reserved_at' => ":reserved_at", 'attempts' => ":attempts"];
        $params = ['id' => $job->id, 'reserved_at' => $job->touch(), 'attempts' => $job->increment()];
        $this->database->query()
            ->table($this->table)
            ->where('id = :id')
            ->save($record, $params);

        return $job;
    }

    /**
     * Delete a reserved job from the queue.
     *
     * @param string $queue
     * @param string $id
     * @return void
     *
     * @throws \Throwable
     */
    public function deleteReserved($queue, $id)
    {
        $this->database->transaction(function () use ($id) {
            $dbJob = $this->database->query()->select('*')
                ->table($this->table)->lockForUpdate()
                ->where('id = :id')
                ->setParameter("id", $id)
                ->first();
            if ($dbJob) {
                $this->database->query()
                    ->delete($this->table)
                    ->where('id = :id')
                    ->setParameter("id", $id)
                    ->execute();
            }
        });
    }

    /**
     * Delete a reserved job from the reserved queue and release it.
     *
     * @param string $queue
     * @param DatabaseJob $job
     * @param int $delay
     * @return void
     */
    public function deleteAndRelease($queue, $job, $delay)
    {
        $this->database->transaction(function () use ($queue, $job, $delay) {
            $dbJob = $this->database->query()->select('*')
                ->table($this->table)
                ->lockForUpdate()
                ->where('id = :id')
                ->setParameter("id", $job->getJobId())
                ->first();
            if ($dbJob) {
                $this->database->query()
                    ->delete($this->table)
                    ->where('id = :id')
                    ->setParameter("id", $job->getJobId())
                    ->execute();
            }

            $this->release($queue, $job->getJobRecord(), $delay);
        });
    }

    /**
     * Delete all the jobs from the queue.
     *
     * @param string $queue
     * @return int
     */
    public function clear($queue)
    {
        return $this->database->query()->delete($this->table)
            ->where('queue = :queue')
            ->setParameter('queue', $this->getQueue($queue))
            ->execute();
    }

    /**
     * Get the queue or return the default.
     *
     * @param string|null $queue
     * @return string
     */
    public function getQueue($queue)
    {
        return $queue ?: $this->default;
    }

    /**
     * Get the underlying database instance.
     *
     * @return DatabaseConnection
     */
    public function getDatabase()
    {
        return $this->database;
    }
}

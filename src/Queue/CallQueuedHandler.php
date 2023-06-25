<?php

namespace Terablaze\Queue;

use Exception;
use Terablaze\Bus\Traits\BatchableTrait;
use Terablaze\Bus\UniqueLock;
use Terablaze\Bus\DispatcherInterface;
use Terablaze\Cache\Psr16\SimpleCacheInterface;
use Terablaze\Container\ContainerInterface;
use Terablaze\Database\ORM\Exception\ModelNotFoundException;
use Terablaze\Encryption\Encrypter;
use Terablaze\Queue\Jobs\JobInterface;
use Terablaze\Bus\Pipeline\Pipeline;
use ReflectionClass;
use RuntimeException;
use Terablaze\Support\Helpers;

class CallQueuedHandler
{
    /**
     * The bus dispatcher implementation.
     *
     * @var \Terablaze\Bus\DispatcherInterface
     */
    protected $dispatcher;

    /**
     * The container instance.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Create a new handler instance.
     *
     * @param  DispatcherInterface  $dispatcher
     * @param  ContainerInterface  $container
     * @return void
     */
    public function __construct(DispatcherInterface $dispatcher, ContainerInterface $container)
    {
        $this->container = $container;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handle the queued job.
     *
     * @param  JobInterface  $job
     * @param  array  $data
     * @return void
     */
    public function call(JobInterface $job, array $data)
    {
        try {
            $command = $this->setJobInstanceIfNecessary(
                $job, $this->getCommand($data)
            );
        } catch (ModelNotFoundException $e) {
            $this->handleModelNotFound($job, $e);
            return;
        }

        if ($command instanceof ShouldBeUniqueUntilProcessing) {
            $this->ensureUniqueJobLockIsReleased($command);
        }

        $this->dispatchThroughMiddleware($job, $command);

        if (! $job->isReleased() && ! $command instanceof ShouldBeUniqueUntilProcessing) {
            $this->ensureUniqueJobLockIsReleased($command);
        }

        if (! $job->hasFailed() && ! $job->isReleased()) {
            $this->ensureNextJobInChainIsDispatched($command);
            $this->ensureSuccessfulBatchJobIsRecorded($command);
        }

        if (! $job->isDeletedOrReleased()) {
            $job->delete();
        }
    }

    /**
     * Get the command from the given payload.
     *
     * @param  array  $data
     * @return mixed
     *
     * @throws \RuntimeException
     */
    protected function getCommand(array $data)
    {
        if (str_starts_with($data['command'], 'O:')) {
            return unserialize($data['command']);
        }

        if ($this->container->has(Encrypter::class)) {
            return unserialize($this->container[Encrypter::class]->decrypt($data['command']));
        }

        throw new RuntimeException('Unable to extract job payload.');
    }

    /**
     * Dispatch the given job / command through its specified middleware.
     *
     * @param  JobInterface  $job
     * @param  mixed  $command
     * @return mixed
     */
    protected function dispatchThroughMiddleware(JobInterface $job, $command)
    {
        if ($command instanceof \__PHP_Incomplete_Class) {
            throw new Exception('Job is incomplete class: '.json_encode($command));
        }

        return (new Pipeline($this->container))->send($command)
                ->through(array_merge(
                    method_exists($command, 'middleware') ? $command->middleware() : [], $command->middleware ?? []
                ))
                ->then(function ($command) use ($job) {
                    return $this->dispatcher->dispatchNow(
                        $command, $this->resolveHandler($job, $command)
                    );
                });
    }

    /**
     * Resolve the handler for the given command.
     *
     * @param  JobInterface  $job
     * @param  mixed  $command
     * @return mixed
     */
    protected function resolveHandler($job, $command)
    {
        $handler = $this->dispatcher->getCommandHandler($command) ?: null;

        if ($handler) {
            $this->setJobInstanceIfNecessary($job, $handler);
        }

        return $handler;
    }

    /**
     * Set the job instance of the given class if necessary.
     *
     * @param  JobInterface  $job
     * @param  mixed  $instance
     * @return mixed
     */
    protected function setJobInstanceIfNecessary(JobInterface $job, $instance)
    {
        if (in_array(InteractsWithQueue::class, Helpers::classUsesRecursive($instance))) {
            $instance->setJob($job);
        }

        return $instance;
    }

    /**
     * Ensure the next job in the chain is dispatched if applicable.
     *
     * @param  mixed  $command
     * @return void
     */
    protected function ensureNextJobInChainIsDispatched($command)
    {
        if (method_exists($command, 'dispatchNextJobInChain')) {
            $command->dispatchNextJobInChain();
        }
    }

    /**
     * Ensure the batch is notified of the successful job completion.
     *
     * @param  mixed  $command
     * @return void
     */
    protected function ensureSuccessfulBatchJobIsRecorded($command)
    {
        $uses = Helpers::classUsesRecursive($command);

        if (! in_array(BatchableTrait::class, $uses) ||
            ! in_array(InteractsWithQueue::class, $uses)) {
            return;
        }

        if ($batch = $command->batch()) {
            $batch->recordSuccessfulJob($command->job->uuid());
        }
    }

    /**
     * Ensure the lock for a unique job is released.
     *
     * @param  mixed  $command
     * @return void
     */
    protected function ensureUniqueJobLockIsReleased($command)
    {
        if ($command instanceof ShouldBeUnique) {
            (new UniqueLock($this->container->make(SimpleCacheInterface::class)))->release($command);
        }
    }

    /**
     * Handle a model not found exception.
     *
     * @param  JobInterface  $job
     * @param  \Throwable  $e
     * @return void
     */
    protected function handleModelNotFound(JobInterface $job, $e)
    {
        $class = $job->resolveName();

        try {
            $shouldDelete = (new ReflectionClass($class))
                    ->getDefaultProperties()['deleteWhenMissingModels'] ?? false;
        } catch (Exception $e) {
            $shouldDelete = false;
        }

        if ($shouldDelete) {
            $job->delete();
            return;
        }

        $job->fail($e);
        return;
    }

    /**
     * Call the failed method on the job instance.
     *
     * The exception that caused the failure will be passed.
     *
     * @param  array  $data
     * @param  \Throwable|null  $e
     * @param  string  $uuid
     * @return void
     */
    public function failed(array $data, $e, string $uuid)
    {
        $command = $this->getCommand($data);

        if (! $command instanceof ShouldBeUniqueUntilProcessing) {
            $this->ensureUniqueJobLockIsReleased($command);
        }

        if ($command instanceof \__PHP_Incomplete_Class) {
            return;
        }

        $this->ensureFailedBatchJobIsRecorded($uuid, $command, $e);
        $this->ensureChainCatchCallbacksAreInvoked($uuid, $command, $e);

        if (method_exists($command, 'failed')) {
            $command->failed($e);
        }
    }

    /**
     * Ensure the batch is notified of the failed job.
     *
     * @param  string  $uuid
     * @param  mixed  $command
     * @param  \Throwable  $e
     * @return void
     */
    protected function ensureFailedBatchJobIsRecorded(string $uuid, $command, $e)
    {
        if (! in_array(BatchableTrait::class, Helpers::classUsesRecursive($command))) {
            return;
        }

        if ($batch = $command->batch()) {
            $batch->recordFailedJob($uuid, $e);
        }
    }

    /**
     * Ensure the chained job catch callbacks are invoked.
     *
     * @param  string  $uuid
     * @param  mixed  $command
     * @param  \Throwable  $e
     * @return void
     */
    protected function ensureChainCatchCallbacksAreInvoked(string $uuid, $command, $e)
    {
        if (method_exists($command, 'invokeChainCatchCallbacks')) {
            $command->invokeChainCatchCallbacks($e);
        }
    }
}

<?php

namespace Terablaze\Queue\Console\Command;

use Carbon\Carbon;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Terablaze\Console\Command;
use Terablaze\Cache\Psr16\SimpleCacheInterface;
use Terablaze\Queue\Jobs\JobInterface;
use Terablaze\Queue\Events\JobFailed;
use Terablaze\Queue\Events\JobProcessed;
use Terablaze\Queue\Events\JobProcessing;
use Terablaze\Queue\Worker;
use Terablaze\Queue\WorkerOptions;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:work')]
class WorkCommand extends Command
{
    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'queue:work';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Start processing jobs on the queue as a daemon';

    /**
     * The queue worker instance.
     *
     * @var \Terablaze\Queue\Worker
     */
    protected $worker;

    /**
     * The cache store implementation.
     *
     * @var SimpleCacheInterface
     */
    protected $cache;

    /**
     * Create a new queue work command.
     *
     * @param  \Terablaze\Queue\Worker  $worker
     * @param  SimpleCacheInterface  $cache
     * @return void
     */
    public function __construct(Worker $worker, SimpleCacheInterface $cache)
    {
        parent::__construct();

        $this->cache = $cache;
        $this->worker = $worker;
    }

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        if ($this->downForMaintenance() && $this->getOption('once')) {
            $this->worker->sleep($this->getOption('sleep'));
            return self::SUCCESS;
        }

        // We'll listen to the processed and failed events so we can write information
        // to the console as jobs are processed, which will let the developer watch
        // which jobs are coming through a queue and be informed on its progress.
        $this->listenForEvents();

        $connection = $this->getArgument('connection')
                        ?: $this->kernel->getConfig()->get('queue.default');

        // We need to get the right queue for the connection which is set in the queue
        // configuration file for the application. We will pull it based on the set
        // connection being run for the queue operation currently being executed.
        $queue = $this->getQueue($connection);

        return $this->runWorker(
            $connection, $queue
        );
    }

    /**
     * Run the worker instance.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @return int|null
     */
    protected function runWorker($connection, $queue)
    {
        return $this->worker->setName($this->getOption('name'))
                     ->setCache($this->cache)
                     ->{$this->getOption('once') ? 'runNextJob' : 'daemon'}(
            $connection, $queue, $this->gatherWorkerOptions()
        );
    }

    /**
     * Gather all of the queue worker options as a single object.
     *
     * @return \Terablaze\Queue\WorkerOptions
     */
    protected function gatherWorkerOptions()
    {
        return new WorkerOptions(
            $this->getOption('name'),
            max($this->getOption('backoff'), $this->getOption('delay')),
            $this->getOption('memory'),
            $this->getOption('timeout'),
            $this->getOption('sleep'),
            $this->getOption('tries'),
            $this->getOption('force'),
            $this->getOption('stop-when-empty'),
            $this->getOption('max-jobs'),
            $this->getOption('max-time'),
            $this->getOption('rest')
        );
    }

    /**
     * Listen for the queue events in order to update the console output.
     *
     * @return void
     */
    protected function listenForEvents()
    {
        $this->kernel->getEventDispatcher()->listen(JobProcessing::class, function ($event) {
            $this->writeOutput($event->job, 'starting');
        });

        $this->kernel->getEventDispatcher()->listen(JobProcessed::class, function ($event) {
            $this->writeOutput($event->job, 'success');
        });

        $this->kernel->getEventDispatcher()->listen(JobFailed::class, function ($event) {
            $this->writeOutput($event->job, 'failed');

            $this->logFailedJob($event);
        });
    }

    /**
     * Write the status output for the queue worker.
     *
     * @param  \Terablaze\Queue\Jobs\JobInterface  $job
     * @param  string  $status
     * @return void
     */
    protected function writeOutput(JobInterface $job, $status)
    {
        switch ($status) {
            case 'starting':
                $this->writeStatus($job, 'Processing', 'comment');
                return;
            case 'success':
                $this->writeStatus($job, 'Processed', 'info');
                return;
            case 'failed':
                $this->writeStatus($job, 'Failed', 'error');
                return;
        }
    }

    /**
     * Format the status output for the queue worker.
     *
     * @param  \Terablaze\Queue\Jobs\JobInterface  $job
     * @param  string  $status
     * @param  string  $type
     * @return void
     */
    protected function writeStatus(JobInterface $job, $status, $type)
    {
        $this->output->writeln(sprintf(
            "<{$type}>[%s][%s] %s</{$type}> %s",
            Carbon::now()->format('Y-m-d H:i:s'),
            $job->getJobId(),
            str_pad("{$status}:", 11), $job->resolveName()
        ));
    }

    /**
     * Store a failed job event.
     *
     * @param  \Terablaze\Queue\Events\JobFailed  $event
     * @return void
     */
    protected function logFailedJob(JobFailed $event)
    {
        $this->container->get('queue.failer')->log(
            $event->connectionName,
            $event->job->getQueue(),
            $event->job->getRawBody(),
            $event->exception
        );
    }

    /**
     * Get the queue name for the worker.
     *
     * @param  string  $connection
     * @return string
     */
    protected function getQueue($connection)
    {
        return $this->getOption('queue') ?: $this->kernel->getConfig()->get(
            "queue.connections.{$connection}.queue", 'default'
        );
    }

    /**
     * Determine if the worker should run in maintenance mode.
     *
     * @return bool
     */
    protected function downForMaintenance()
    {
        return $this->getOption('force') ? false : $this->kernel->isDownForMaintenance();
    }

    protected function getArguments()
    {
        return [
            ['connection', InputArgument::OPTIONAL, 'The name of the queue connection to work'],
        ];
    }

    protected function getOptions()
    {
        return [
            ["name", null, InputOption::VALUE_OPTIONAL, 'The name of the worker', 'default'],
            ["queue", null, InputOption::VALUE_OPTIONAL, 'The names of the queues to work'],
            ["daemon", null, InputOption::VALUE_OPTIONAL, 'Run the worker in daemon mode (Deprecated)'],
            ["once", null, InputOption::VALUE_OPTIONAL, 'Only process the next job on the queue'],
            ["stop-when-empty", null, InputOption::VALUE_OPTIONAL, 'Stop when the queue is empty'],
            ["delay", null, InputOption::VALUE_OPTIONAL, 'The number of seconds to delay failed jobs (Deprecated)', 0],
            ["backoff", null, InputOption::VALUE_OPTIONAL, 'The number of seconds to wait before retrying a job that encountered an uncaught exception', 0],
            ["max-jobs", null, InputOption::VALUE_OPTIONAL, 'The number of jobs to process before stopping', 0],
            ["max-time", null, InputOption::VALUE_OPTIONAL, 'The maximum number of seconds the worker should run', 0],
            ["force", null, InputOption::VALUE_OPTIONAL, 'Force the worker to run even in maintenance mode'],
            ["memory", null, InputOption::VALUE_OPTIONAL, 'The memory limit in megabytes', 128],
            ["sleep", null, InputOption::VALUE_OPTIONAL, 'Number of seconds to sleep when no job is available', 3],
            ["rest", null, InputOption::VALUE_OPTIONAL, 'Number of seconds to rest between jobs', 0],
            ["timeout", null, InputOption::VALUE_OPTIONAL, 'The number of seconds a child process can run', 60],
            ["tries", null, InputOption::VALUE_OPTIONAL, 'Number of times to attempt a job before logging it failed}', 1],
        ];
    }
}

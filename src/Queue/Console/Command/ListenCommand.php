<?php

namespace Terablaze\Queue\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Terablaze\Console\Command;
use Terablaze\Queue\Listener;
use Terablaze\Queue\ListenerOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Terablaze\Support\Helpers;

#[AsCommand(name: 'queue:listen')]
class ListenCommand extends Command
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
    protected static $defaultName = 'queue:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to a given queue';

    /**
     * The queue listener instance.
     *
     * @var \Terablaze\Queue\Listener
     */
    protected $listener;

    /**
     * Create a new queue listen command.
     *
     * @param  \Terablaze\Queue\Listener  $listener
     * @return void
     */
    public function __construct(Listener $listener)
    {
        parent::__construct();

        $this->setOutputHandler($this->listener = $listener);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // We need to get the right queue for the connection which is set in the queue
        // configuration file for the application. We will pull it based on the set
        // connection being run for the queue operation currently being executed.
        $queue = $this->getQueue(
            $connection = $this->input->getArgument('connection')
        );

        $this->listener->listen(
            $connection, $queue, $this->gatherOptions()
        );

        return self::SUCCESS;
    }

    /**
     * Get the name of the queue connection to listen on.
     *
     * @param  string  $connection
     * @return string
     */
    protected function getQueue($connection)
    {
        $connection = $connection ?: Helpers::getConfig('queue.default');

        return $this->input->getOption('queue') ?:
            Helpers::getConfig("queue.connections.{$connection}.queue", 'default');
    }

    /**
     * Get the listener options for the command.
     *
     * @return \Terablaze\Queue\ListenerOptions
     */
    protected function gatherOptions()
    {
        $backoff = $this->getDefinition()->hasOption('backoff')
                ? $this->getOption('backoff')
                : $this->getOption('delay');

        return new ListenerOptions(
            $this->getOption('name'),
            $this->getOption('env'),
            $backoff,
            $this->getOption('memory'),
            $this->getOption('timeout'),
            $this->getOption('sleep'),
            $this->getOption('tries'),
            $this->getOption('force')
        );
    }

    /**
     * Set the options on the queue listener.
     *
     * @param  \Terablaze\Queue\Listener  $listener
     * @return void
     */
    protected function setOutputHandler(Listener $listener)
    {
        $listener->setOutputHandler(function ($type, $line) {
            $this->output->write($line);
        });
    }

    public function getArguments()
    {
        return [
            ['connection', InputArgument::OPTIONAL, 'The name of connection'],
        ];
    }

    public function getOptions()
    {
        return [
            ['name', null, InputOption::VALUE_OPTIONAL, 'The name of the worker', 'default'],
            ['delay', null, InputOption::VALUE_OPTIONAL, 'The number of seconds to delay failed jobs (Deprecated)', 0],
            ['backoff', null, InputOption::VALUE_OPTIONAL, 'The number of seconds to wait before retrying a job that encountered an uncaught exception', 0],
            ['force' , null, InputOption::VALUE_OPTIONAL, 'the worker to run even in maintenance mode'],
            ['memory', null, InputOption::VALUE_OPTIONAL, 'The memory limit in megabytes', 128],
            ['queue', null, InputOption::VALUE_OPTIONAL, 'queue to listen on'],
            ['sleep', null, InputOption::VALUE_OPTIONAL, 'Number of seconds to sleep when no job is available', 3],
            ['timeout', null, InputOption::VALUE_OPTIONAL, 'The number of seconds a child process can run', 60],
            ['tries', null, InputOption::VALUE_OPTIONAL, 'Number of times to attempt a job before logging it failed', 1],
        ];
    }
}

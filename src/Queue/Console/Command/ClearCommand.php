<?php

namespace Terablaze\Queue\Console\Command;

use Terablaze\Console\Command;
use Terablaze\Console\ConfirmableTrait;
use Terablaze\Queue\ClearableQueueInterface;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Terablaze\Queue\QueueManager;
use Terablaze\Support\Helpers;

#[AsCommand(name: 'queue:clear')]
class ClearCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'queue:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = "Delete all of the jobs from the specified queue";

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        if (! $this->confirmToProceed()) {
            return 1;
        }

        $connection = $this->getArgument('connection')
                        ?: Helpers::getConfig('queue.default');

        // We need to get the right queue for the connection which is set in the queue
        // configuration file for the application. We will pull it based on the set
        // connection being run for the queue operation currently being executed.
        $queueName = $this->getQueue($connection);

        /** @var QueueManager $queue */
        $queue = $this->container->get('queue')->connection($connection);

        if ($queue instanceof ClearableQueueInterface) {
            $count = $queue->clear($queueName);

            $this->io->info('Cleared '.$count.' jobs from the ['.$queueName.'] queue');
        } else {
            $this->io->error('Clearing queues is not supported on ['.(new ReflectionClass($queue))->getShortName().']');
        }

        return self::SUCCESS;
    }

    /**
     * Get the queue name to clear.
     *
     * @param  string  $connection
     * @return string
     */
    protected function getQueue($connection)
    {
        return $this->getOption('queue') ?: Helpers::getConfig(
            "queue.connections.{$connection}.queue", 'default'
        );
    }

    /**
     *  Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['connection', InputArgument::OPTIONAL, 'The name of the queue connection to clear'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['queue', null, InputOption::VALUE_OPTIONAL, 'The name of the queue to clear'],

            ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production'],
        ];
    }
}

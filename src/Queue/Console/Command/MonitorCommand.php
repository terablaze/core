<?php

namespace Terablaze\Queue\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Terablaze\Console\Command;
use Terablaze\EventDispatcher\Dispatcher;
use Terablaze\Queue\FactoryInterface;
use Terablaze\Queue\Events\QueueBusy;
use Terablaze\Collection\CollectionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Terablaze\Support\Helpers;

#[AsCommand(name: 'queue:monitor')]
class MonitorCommand extends Command
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
    protected static $defaultName = 'queue:monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription= 'Monitor the size of the specified queues';

    /**
     * The queue manager instance.
     *
     * @var \Terablaze\Queue\FactoryInterface
     */
    protected $manager;

    /**
     * The events dispatcher instance.
     *
     * @var Dispatcher
     */
    protected $events;

    /**
     * The table headers for the command.
     *
     * @var string[]
     */
    protected $headers = ['Connection', 'Queue', 'Size', 'Status'];

    /**
     * Create a new queue listen command.
     *
     * @param \Terablaze\Queue\FactoryInterface $manager
     * @param Dispatcher $events
     * @return void
     */
    public function __construct(FactoryInterface $manager, Dispatcher $events)
    {
        parent::__construct();

        $this->manager = $manager;
        $this->events = $events;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $queues = $this->parseQueues($this->getArgument('queues'));

        $this->displaySizes($queues);

        $this->dispatchEvents($queues);
    }

    /**
     * Parse the queues into an array of the connections and queues.
     *
     * @param string $queues
     * @return CollectionInterface
     */
    protected function parseQueues($queues)
    {
        return collect(explode(',', $queues))->map(function ($queue) {
            [$connection, $queue] = array_pad(explode(':', $queue, 2), 2, null);

            if (!isset($queue)) {
                $queue = $connection;
                $connection = Helpers::getConfig('queue.default');
            }

            return [
                'connection' => $connection,
                'queue' => $queue,
                'size' => $size = $this->manager->connection($connection)->size($queue),
                'status' => $size >= $this->getOption('max') ? '<fg=red>ALERT</>' : 'OK',
            ];
        });
    }

    /**
     * Display the failed jobs in the console.
     *
     * @param CollectionInterface $queues
     * @return void
     */
    protected function displaySizes(CollectionInterface $queues)
    {
        $this->io->table($this->headers, $queues->toArray());
    }

    /**
     * Fire the monitoring events.
     *
     * @param CollectionInterface $queues
     * @return void
     */
    protected function dispatchEvents(CollectionInterface $queues)
    {
        foreach ($queues as $queue) {
            if ($queue['status'] == 'OK') {
                continue;
            }

            $this->events->dispatch(
                new QueueBusy(
                    $queue['connection'],
                    $queue['queue'],
                    $queue['size'],
                )
            );
        }
    }

    public function getArguments()
    {
        return [
            ['queues', InputArgument::OPTIONAL, 'The names of the queues to monitor'],
        ];
    }

    public function getOptions()
    {
        return [
            [
                'max',
                null,
                InputOption::VALUE_OPTIONAL,
                'The maximum number of jobs that can be on the queue before an event is dispatchedr',
                1000
            ],
        ];
    }
}

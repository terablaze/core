<?php

namespace Terablaze\Core\Scheduling\Console\Command;

use Carbon\Carbon;
use Symfony\Component\Console\Attribute\AsCommand;
use Terablaze\Console\Application;
use Terablaze\Console\Command;
use Terablaze\Console\View\Components\Task;
use Terablaze\Core\Scheduling\CallbackEvent;
use Terablaze\Core\Scheduling\Event;
use Terablaze\Core\Scheduling\Events\ScheduledTaskFailed;
use Terablaze\Core\Scheduling\Events\ScheduledTaskFinished;
use Terablaze\Core\Scheduling\Events\ScheduledTaskSkipped;
use Terablaze\Core\Scheduling\Events\ScheduledTaskStarting;
use Terablaze\Core\Scheduling\Schedule;
use Terablaze\ErrorHandler\ExceptionHandlerInterface;
use Terablaze\EventDispatcher\Dispatcher;
use Throwable;

#[AsCommand(name: 'schedule:run', description: 'Run the scheduled commands')]
class ScheduleRunCommand extends Command
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
    protected static $defaultName = 'schedule:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Run the scheduled commands';

    /**
     * The schedule instance.
     *
     * @var Schedule
     */
    protected $schedule;

    /**
     * The 24-hour timestamp this scheduler command started running.
     *
     * @var Carbon
     */
    protected $startedAt;

    /**
     * Check if any events ran.
     *
     * @var bool
     */
    protected $eventsRan = false;

    /**
     * The event dispatcher.
     *
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * The exception handler.
     *
     * @var ExceptionHandlerInterface
     */
    protected $handler;

    /**
     * The PHP binary used by the command.
     *
     * @var string
     */
    protected $phpBinary;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->startedAt = Carbon::now();

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param  Schedule  $schedule
     * @param  Dispatcher  $dispatcher
     * @param  ExceptionHandlerInterface  $handler
     * @return void
     */
    public function handle(Schedule $schedule, Dispatcher $dispatcher, ExceptionHandlerInterface $handler)
    {
        $this->schedule = $schedule;
        $this->dispatcher = $dispatcher;
        $this->handler = $handler;
        $this->phpBinary = Application::phpBinary();

        $this->io->newLine();

        foreach ($this->schedule->dueEvents($this->kernel) as $event) {
            if (! $event->filtersPass($this->container)) {
                $this->dispatcher->dispatch(new ScheduledTaskSkipped($event));

                continue;
            }

            if ($event->onOneServer) {
                $this->runSingleServerEvent($event);
            } else {
                $this->runEvent($event);
            }

            $this->eventsRan = true;
        }

        if (! $this->eventsRan) {
            $this->io->info('No scheduled commands are ready to run.');
        } else {
            $this->io->newLine();
        }
    }

    /**
     * Run the given single server event.
     *
     * @param  Event  $event
     * @return void
     */
    protected function runSingleServerEvent($event)
    {
        if ($this->schedule->serverShouldRun($event, $this->startedAt)) {
            $this->runEvent($event);
        } else {
            $this->io->info(sprintf(
                'Skipping [%s], as command already run on another server.', $event->getSummaryForDisplay()
            ));
        }
    }

    /**
     * Run the given event.
     *
     * @param  Event  $event
     * @return void
     */
    protected function runEvent($event)
    {
        $summary = $event->getSummaryForDisplay();

        $command = $event instanceof CallbackEvent
            ? $summary
            : trim(str_replace($this->phpBinary, '', $event->command));

        $description = sprintf(
            '<fg=gray>%s</> Running [%s]%s',
            Carbon::now()->format('Y-m-d H:i:s'),
            $command,
            $event->runInBackground ? ' in background' : '',
        );

        (new Task($this->io))->render($description, function () use ($event) {
            $this->dispatcher->dispatch(new ScheduledTaskStarting($event));

            $start = microtime(true);

            try {
                $event->run($this->container);

                $this->dispatcher->dispatch(new ScheduledTaskFinished(
                    $event,
                    round(microtime(true) - $start, 2)
                ));

                $this->eventsRan = true;
            } catch (Throwable $e) {
                $this->dispatcher->dispatch(new ScheduledTaskFailed($event, $e));

                $this->handler->report($e);
            }

            return $event->exitCode == 0;
        });

        if (! $event instanceof CallbackEvent) {
            $this->io->listing([
                $event->getSummaryForDisplay(),
            ]);
        }
    }
}

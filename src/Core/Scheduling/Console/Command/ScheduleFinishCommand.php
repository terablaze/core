<?php

namespace Terablaze\Core\Scheduling\Console\Command;

use Terablaze\Core\Scheduling\Events\ScheduledBackgroundTaskFinished;
use Symfony\Component\Console\Attribute\AsCommand;
use Terablaze\Console\Command;
use Terablaze\Core\Scheduling\Schedule;

#[AsCommand(name: 'schedule:finish')]
class ScheduleFinishCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'schedule:finish {id} {code=0}';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'schedule:finish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle the completion of a scheduled command';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     *
     * @param  \Terablaze\Core\Scheduling\Schedule  $schedule
     * @return void
     */
    public function handle(Schedule $schedule)
    {
        collect($schedule->events())->filter(function ($value) {
            return $value->mutexName() == $this->getArgument('id');
        })->each(function ($event) {
            $event->finish($this->kernel, $this->getArgument('code'));

            $this->kernel->getEventDispatcher()->dispatch(new ScheduledBackgroundTaskFinished($event));
        });
    }
}

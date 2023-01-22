<?php

namespace Terablaze\Core\Scheduling\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Terablaze\Console\Command;
use Terablaze\Core\Scheduling\Schedule;

#[AsCommand(name: 'schedule:clear-cache', description: 'Delete the cached mutex files created by scheduler')]
class ScheduleClearCacheCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected static $defaultName = 'schedule:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Delete the cached mutex files created by scheduler';

    /**
     * Execute the console command.
     *
     * @param  \Terablaze\Core\Scheduling\Schedule  $schedule
     * @return void
     */
    public function handle(Schedule $schedule)
    {
        $mutexCleared = false;

        foreach ($schedule->events($this->kernel) as $event) {
            if ($event->mutex->exists($event)) {
                $this->io->info(sprintf('Deleting mutex for [%s]', $event->command));

                $event->mutex->forget($event);

                $mutexCleared = true;
            }
        }

        if (! $mutexCleared) {
            $this->io->info('No mutex files were found.');
        }
    }
}

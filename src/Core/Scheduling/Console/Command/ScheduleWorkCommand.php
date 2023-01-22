<?php

namespace Terablaze\Core\Scheduling\Console\Command;

use Carbon\Carbon;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process;
use Terablaze\Console\Command;

#[AsCommand(name: 'schedule:work', description: 'Start the schedule worker')]
class ScheduleWorkCommand extends Command
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
    protected static $defaultName = 'schedule:work';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Start the schedule worker';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->io->info('Running schedule tasks every minute.');

        [$lastExecutionStartedAt, $executions] = [null, []];

        while (true) {
            usleep(100 * 1000);

            if (Carbon::now()->second === 0 &&
                ! Carbon::now()->startOfMinute()->equalTo($lastExecutionStartedAt)) {
                $executions[] = $execution = new Process([
                    PHP_BINARY,
                    defined('BLAZE_BINARY') ? BLAZE_BINARY : 'blaze',
                    'schedule:run',
                ]);

                $execution->start();

                $lastExecutionStartedAt = Carbon::now()->startOfMinute();
            }

            foreach ($executions as $key => $execution) {
                $output = $execution->getIncrementalOutput().
                          $execution->getIncrementalErrorOutput();

                $this->output->write(ltrim($output, "\n"));

                if (! $execution->isRunning()) {
                    unset($executions[$key]);
                }
            }
        }
    }
}

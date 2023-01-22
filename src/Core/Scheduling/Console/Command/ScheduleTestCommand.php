<?php

namespace Terablaze\Core\Scheduling\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Terablaze\Console\Application;
use Terablaze\Console\Command;
use Terablaze\Console\View\Components\Task;
use Terablaze\Core\Scheduling\CallbackEvent;
use Terablaze\Core\Scheduling\Schedule;

#[AsCommand(name: 'schedule:test', description: 'Run a scheduled command')]
class ScheduleTestCommand extends Command
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
    protected static $defaultName = 'schedule:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Run a scheduled command';

    /**
     * Execute the console command.
     *
     * @param  Schedule  $schedule
     * @return int
     */
    public function handle(Schedule $schedule)
    {
        $phpBinary = Application::phpBinary();

        $commands = $schedule->events();

        $commandNames = [];

        foreach ($commands as $command) {
            $commandNames[] = $command->command ?? $command->getSummaryForDisplay();
        }

        if (empty($commandNames)) {
            $this->io->info('No scheduled commands have been defined.');
            return self::SUCCESS;
        }

        if (! empty($name = $this->getOption('name'))) {
            $commandBinary = $phpBinary.' '.Application::blazeBinary();

            $matches = array_filter($commandNames, function ($commandName) use ($commandBinary, $name) {
                return trim(str_replace($commandBinary, '', $commandName)) === $name;
            });

            if (count($matches) !== 1) {
                $this->io->info('No matching scheduled command found.');

                return self::SUCCESS;
            }

            $index = key($matches);
        } else {
            $index = array_search($this->io->choice('Which command would you like to run?', $commandNames), $commandNames);
        }

        $event = $commands[$index];

        $summary = $event->getSummaryForDisplay();

        $command = $event instanceof CallbackEvent
            ? $summary
            : trim(str_replace($phpBinary, '', $event->command));

        $description = sprintf(
            'Running [%s]%s',
            $command,
            $event->runInBackground ? ' in background' : '',
        );

        (new Task($this->io))->render($description, fn () => $event->run($this->container));

        if (! $event instanceof CallbackEvent) {
            $this->io->listing([$event->getSummaryForDisplay()]);
        }

        $this->io->newLine();
        return self::SUCCESS;
    }

    protected function getOptions()
    {
        return [
            ['name', null, InputOption::VALUE_REQUIRED, 'The name of the scheduled command to run'],
        ];
    }
}

<?php

namespace Terablaze\Queue\Console\Command;

use Symfony\Component\Console\Input\InputOption;
use Terablaze\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:flush')]
class FlushFailedCommand extends Command
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
    protected static $defaultName = 'queue:flush';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Flush all of the failed queue jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->container->get('queue.failer')->flush($this->getOption('hours'));

        if ($this->getOption('hours')) {
            $this->io->info("All jobs that failed more than {$this->getOption('hours')} " .
                "hours ago have been deleted successfully.");

            return self::SUCCESS;
        }

        $this->io->info('All failed jobs deleted successfully.');

        return self::SUCCESS;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['hours', null, InputOption::VALUE_OPTIONAL, 'The number of hours to retain failed job data'],
        ];
    }
}

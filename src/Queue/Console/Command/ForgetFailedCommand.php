<?php

namespace Terablaze\Queue\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Terablaze\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:forget')]
class ForgetFailedCommand extends Command
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
    protected static $defaultName = 'queue:forget';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a failed queue job';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->container->get('queue.failer')->forget($this->getArgument('id'))) {
            $this->io->info('Failed job deleted successfully.');
        } else {
            $this->io->error('No failed job matches the given ID.');
        }

        return self::SUCCESS;
    }

    /**
     *  Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['id', InputArgument::OPTIONAL, 'The ID of the failed job'],
        ];
    }
}

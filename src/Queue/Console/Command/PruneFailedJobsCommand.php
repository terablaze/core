<?php

namespace Terablaze\Queue\Console\Command;

use Carbon\Carbon;
use Symfony\Component\Console\Input\InputOption;
use Terablaze\Console\Command;
use Terablaze\Queue\Failed\PrunableFailedJobProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Terablaze\Support\Helpers;

#[AsCommand(name: 'queue:prune-failed')]
class PruneFailedJobsCommand extends Command
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
    protected static $defaultName = 'queue:prune-failed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune stale entries from the failed jobs table';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        $failer = $this->container->get('queue.failer');

        if ($failer instanceof PrunableFailedJobProvider) {
            $count = $failer->prune(Carbon::now()->subHours($this->getOption('hours')));
        } else {
            $this->io->error('The ['.Helpers::classBasename($failer).'] ' .
                'failed job storage driver does not support pruning.');

            return self::FAILURE;
        }

        $this->io->info("{$count} entries deleted!");
        return self::SUCCESS;
    }

    public function getOptions()
    {
        return [
            ["hours", null, InputOption::VALUE_OPTIONAL, "The number of hours to retain failed jobs data", 24],
        ];
    }
}

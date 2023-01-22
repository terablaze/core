<?php

namespace Terablaze\Queue\Console\Command;

use Carbon\Carbon;
use Symfony\Component\Console\Input\InputOption;
use Terablaze\Bus\BatchRepositoryInterface;
use Terablaze\Bus\DatabaseBatchRepository;
use Terablaze\Bus\PrunableBatchRepositoryInterface;
use Terablaze\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:prune-batches')]
class PruneBatchesCommand extends Command
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
    protected static $defaultName = 'queue:prune-batches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Prune stale entries from the batches database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var BatchRepositoryInterface $repository */
        $repository = $this->container->get(BatchRepositoryInterface::class);

        $count = 0;

        if ($repository instanceof PrunableBatchRepositoryInterface) {
            $count = $repository->prune(Carbon::now()->subHours($this->getOption('hours')));
        }

        $this->io->info("{$count} entries deleted!");

        if ($this->getOption('unfinished')) {
            $count = 0;

            if ($repository instanceof DatabaseBatchRepository) {
                $count = $repository->pruneUnfinished(Carbon::now()->subHours($this->getOption('unfinished')));
            }

            $this->io->info("{$count} unfinished entries deleted!");
        }
        return self::SUCCESS;
    }

    public function getOptions()
    {
        return [
                ["hours", null, InputOption::VALUE_OPTIONAL, "The number of hours to retain batch data", 24],
                ["unfinished", null, InputOption::VALUE_OPTIONAL, "The number of hours to retain unfinished batch data"],
        ];
    }
}

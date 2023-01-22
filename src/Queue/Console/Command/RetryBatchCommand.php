<?php

namespace Terablaze\Queue\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Terablaze\Bus\BatchRepositoryInterface;
use Terablaze\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:retry-batch')]
class RetryBatchCommand extends Command
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
    protected static $defaultName = 'queue:retry-batch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription= 'Retry the failed jobs for a batch';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        $batch = $this->container->get(BatchRepositoryInterface::class)->find($id = $this->getArgument('id'));

        if (! $batch) {
            $this->io->error("Unable to find a batch with ID [{$id}].");

            return self::FAILURE;
        } elseif (empty($batch->failedJobIds)) {
            $this->io->error('The given batch does not contain any failed jobs.');

            return self::FAILURE;
        }

        foreach ($batch->failedJobIds as $failedJobId) {
            $this->call('queue:retry', ['id' => $failedJobId]);
        }
        return self::SUCCESS;
    }

    public function getArguments()
    {
        return [
            ['id', InputArgument::OPTIONAL, 'The ID of the batch whose failed jobs should be retried']
        ];
    }
}

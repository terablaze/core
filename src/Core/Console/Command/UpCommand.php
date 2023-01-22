<?php

namespace Terablaze\Core\Console\Command;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Terablaze\Console\Command;
use Terablaze\Core\MaintenanceMode\Events\MaintenanceModeDisabled;

#[AsCommand(name: 'up', description: 'Bring the application out of maintenance mode')]
class UpCommand extends Command
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
    protected static $defaultName = 'up';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Bring the application out of maintenance mode';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            if (! $this->kernel->maintenanceMode()->active()) {
                $this->io->info('Application is already up.');

                return 0;
            }

            $this->kernel->maintenanceMode()->deactivate();

            if (is_file(storageDir('framework/maintenance.php'))) {
                unlink(storageDir('framework/maintenance.php'));
            }

            $this->kernel->getEventDispatcher()->dispatch(new MaintenanceModeDisabled());

            $this->io->info('Application is now live.');
        } catch (Exception $e) {
            $this->io->error(sprintf(
                'Failed to disable maintenance mode: %s.',
                $e->getMessage(),
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

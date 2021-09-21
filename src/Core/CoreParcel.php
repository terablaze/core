<?php

namespace TeraBlaze\Core;

use TeraBlaze\Console\Application;
use TeraBlaze\Core\Console\Command\KeyGenerateCommand;
use TeraBlaze\Core\Console\Command\StorageLinkCommand;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;

class CoreParcel extends Parcel implements ParcelInterface
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected array $commands = [
        KeyGenerateCommand::class,
        StorageLinkCommand::class,
    ];

    public function boot(): void
    {
    }

    public function registerCommands(Application $application)
    {
        $this->registerCoreCommands($application, $this->commands);
    }

    /**
     * Register the given commands.
     *
     * @param array $commands
     * @return void
     */
    protected function registerCoreCommands(Application $application, array $commands)
    {
        foreach ($commands as $command) {
            $application->add($this->container->make($command));
        }
    }
}

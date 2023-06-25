<?php

namespace Terablaze\Core;

use Terablaze\Console\Application;
use Terablaze\Core\Console\Command\DownCommand;
use Terablaze\Core\Console\Command\JobMakeCommand;
use Terablaze\Core\Console\Command\KeyGenerateCommand;
use Terablaze\Core\Console\Command\MailMakeCommand;
use Terablaze\Core\Console\Command\NotificationMakeCommand;
use Terablaze\Core\Console\Command\ServeCommand;
use Terablaze\Core\Console\Command\StorageLinkCommand;
use Terablaze\Core\Console\Command\UpCommand;
use Terablaze\Core\Parcel\Parcel;
use Terablaze\Core\Parcel\ParcelInterface;
use Terablaze\Core\Scheduling\Console\Command\ScheduleClearCacheCommand;
use Terablaze\Core\Scheduling\Console\Command\ScheduleFinishCommand;
use Terablaze\Core\Scheduling\Console\Command\ScheduleListCommand;
use Terablaze\Core\Scheduling\Console\Command\ScheduleRunCommand;
use Terablaze\Core\Scheduling\Console\Command\ScheduleTestCommand;
use Terablaze\Core\Scheduling\Console\Command\ScheduleWorkCommand;

class CoreParcel extends Parcel implements ParcelInterface
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected array $commands = [
        DownCommand::class,
        KeyGenerateCommand::class,
        ServeCommand::class,
        StorageLinkCommand::class,
        UpCommand::class,
        JobMakeCommand::class,
        MailMakeCommand::class,
        NotificationMakeCommand::class,

        ScheduleClearCacheCommand::class,
        ScheduleFinishCommand::class,
        ScheduleListCommand::class,
        ScheduleRunCommand::class,
        ScheduleTestCommand::class,
        ScheduleWorkCommand::class,
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

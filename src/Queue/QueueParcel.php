<?php

namespace TeraBlaze\Queue;

use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Queue\Exception\ArgumentException;

class QueueParcel extends Parcel implements ParcelInterface
{
    public function boot(): void
    {
        $parsed = loadConfig('queue');

        foreach ($parsed->get('queue.connections') as $key => $conf) {
            $this->initialize($key, $conf);
        }
    }

    /**
     * @throws ArgumentException
     * @throws \TeraBlaze\Container\Exception\ServiceNotFoundException
     * @throws \ReflectionException
     */
    private function initialize(string $confKey, array $conf): void
    {
        $type = $conf['type'] ?? $conf['driver'] ?? '';

        $connectionName = "queue.connection.$confKey";
        if (empty($type)) {
            throw new ArgumentException("Database driver type not set");
        }

        switch ($type) {
            case "mysql":
            case "mysqli":
                $queueConnection = (new MysqlConnection($conf))
                    ->setName($confKey)->setEventDispatcher($this->dispatcher);
                break;
            default:
                throw new ArgumentException(sprintf("Invalid or unimplemented queue connection type: %s", $type));
        }
        $this->container->registerServiceInstance($connectionName, $dbConnection);
        if (getConfig('database.default') === $confKey) {
            if ($dbConnection instanceof ConnectorInterface) {
                $this->container->setAlias(ConnectorInterface::class, $connectionName);
                return;
            }
            $this->container->setAlias(ConnectionInterface::class, $connectionName);
            $this->container->setAlias('database.connection', $connectionName);
            $this->container->setAlias('database.connection.default', $connectionName);
        }
    }

    /**
     * @throws ReflectionException
     */
    public function registerCommands(Application $application)
    {
        if (! $this->getKernel()->inConsole()) {
            return;
        }
        $this->registerRepository();

        $this->registerMigrator();

        $this->registerCreator();

        $this->registerMigrationCommands($application, $this->commands);
    }

    /**
     * Register the migration repository service.
     *
     * @return void
     */
    protected function registerRepository()
    {
        $this->container->make(MigrationRepository::class);
    }

    /**
     * Register the migrator service.
     *
     * @return void
     */
    protected function registerMigrator()
    {
        // The migrator is responsible for actually running and rollback the migration
        // files in the application. We'll pass in our database connection resolver
        // so the migrator can resolve any of these connections when it needs to.
        $this->container->make(Migrator::class);
    }

    /**
     * Register the migration creator.
     *
     * @return void
     * @throws ReflectionException
     */
    protected function registerCreator()
    {
        $this->container->make(MigrationCreator::class, [
            'class' => MigrationCreator::class,
            'arguments' => [
                MigrationCreator::class,
                $this->getKernel()->getProjectDir() . DIRECTORY_SEPARATOR . 'stubs',
            ]
        ]);
    }

    /**
     * Register the given commands.
     *
     * @param array $commands
     * @return void
     */
    protected function registerMigrationCommands(Application $application, array $commands)
    {
        foreach ($commands as $command) {
            $application->add($this->container->make($command));
        }
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateCommand()
    {
        return $this->container->make(MigrateCommand::class);
    }

//    /**
//     * Register the command.
//     *
//     * @return void
//     */
//    protected function registerMigrateFreshCommand()
//    {
//        return $this->container->make(FreshCommand::class);
//    }

//    /**
//     * Register the command.
//     *
//     * @return void
//     */
//    protected function registerMigrateInstallCommand()
//    {
//        return $this->container->make(InstallCommand::class);
//    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateMakeCommand()
    {
        return $this->container->make(MigrateMakeCommand::class);
    }

//    /**
//     * Register the command.
//     *
//     * @return void
//     */
//    protected function registerMigrateRefreshCommand()
//    {
//        return $this->container->make(RefreshCommand::class);
//    }

//    /**
//     * Register the command.
//     *
//     * @return void
//     */
//    protected function registerMigrateResetCommand()
//    {
//        return $this->container->make(ResetCommand::class);
//    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateRollbackCommand()
    {
        return $this->container->make(RollbackCommand::class);
    }

//    /**
//     * Register the command.
//     *
//     * @return void
//     */
//    protected function registerMigrateStatusCommand()
//    {
//        return $this->container->make(StatusCommand::class);
//    }
}

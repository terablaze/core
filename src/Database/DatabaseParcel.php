<?php

namespace TeraBlaze\Database;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\PsrCachedReader;
use Doctrine\Common\Annotations\Reader;
use ReflectionException;
use TeraBlaze\Config\Exception\InvalidContextException;
use TeraBlaze\Container\Exception\ServiceNotFoundException;
use TeraBlaze\Console\Application;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Database\Connection\ConnectionInterface;
use TeraBlaze\Database\Connection\MysqlConnection;
use TeraBlaze\Database\Connection\SqliteConnection;
use TeraBlaze\Database\Console\Command\Migrations\InstallCommand;
use TeraBlaze\Database\Console\Command\Migrations\MigrateCommand;
use TeraBlaze\Database\Console\Command\Migrations\MigrateMakeCommand;
use TeraBlaze\Database\Console\Command\Migrations\RollbackCommand;
use TeraBlaze\Database\Exception\ArgumentException;
use TeraBlaze\Database\Migrations\MigrationCreator;
use TeraBlaze\Database\Migrations\MigrationRepository;
use TeraBlaze\Database\Migrations\Migrator;
use TeraBlaze\Database\ORM\AnnotationDriver;
use TeraBlaze\Database\ORM\ClassMetadata;
use Tests\TeraBlaze\Model\Book;

class DatabaseParcel extends Parcel implements ParcelInterface
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = [
        MigrateCommand::class,
//        MigrateFreshCommand::class,
        InstallCommand::class,
//        MigrateRefreshCommand::class,
//        MigrateResetCommand::class,
        RollbackCommand::class,
//        MigrateStatusCommand::class,
        MigrateMakeCommand::class,
    ];

    /**
     * @throws ArgumentException
     * @throws InvalidContextException
     * @throws ReflectionException
     * @throws ServiceNotFoundException
     */
    public function boot(): void
    {
        $parsed = loadConfig('database');

        foreach ($parsed->get('database.connections') as $key => $conf) {
            $this->initialize($key, $conf);
        }
    }

    /**
     * @param string $confKey
     * @param array<string, mixed> $conf
     * @throws ArgumentException
     * @throws ReflectionException
     * @throws ServiceNotFoundException
     */
    private function initialize(string $confKey, array $conf): void
    {
        $type = $conf['type'] ?? $conf['driver'] ?? '';

        $connectionName = "database.connection.$confKey";
        if (empty($type)) {
            throw new ArgumentException("Database driver type not set");
        }

        switch ($type) {
            case "mysql":
            case "mysqli":
                $dbConnection = (new MysqlConnection($conf))
                    ->setName($confKey)->setEventDispatcher($this->dispatcher);
                break;
            case "sqlite":
                $dbConnection = (new SqliteConnection($conf))
                    ->setName($confKey)->setEventDispatcher($this->dispatcher);
                break;
            default:
                throw new ArgumentException(sprintf("Invalid or unimplemented database type: %s", $type));
        }
        $this->container->registerServiceInstance($connectionName, $dbConnection);
        if (getConfig('database.default') === $confKey) {
            $this->container->setAlias(ConnectionInterface::class, $connectionName);
            $this->container->setAlias('database.connection', $connectionName);
            $this->container->setAlias('database.connection.default', $connectionName);
        }

        $this->initAnnotationDriver();
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

    private function initAnnotationDriver($paths = [])
    {
        $annotationDriver = new AnnotationDriver(
            new AnnotationReader(),
            (array) $paths
        );
        $this->container->registerServiceInstance($annotationDriver);
    }
}

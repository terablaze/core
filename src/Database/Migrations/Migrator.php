<?php

namespace Terablaze\Database\Migrations;

use Symfony\Component\Console\Output\OutputInterface;
use Terablaze\Collection\ArrayCollection;
use Terablaze\Container\ContainerInterface;
use Terablaze\Core\Parcel\ParcelInterface;
use Terablaze\Database\Connection\ConnectionInterface;
use Terablaze\Database\Events\MigrationEnded;
use Terablaze\Database\Events\MigrationsEnded;
use Terablaze\Database\Events\MigrationsStarted;
use Terablaze\Database\Events\MigrationStarted;
use Terablaze\Database\Events\NoPendingMigrations;
use Terablaze\Database\Exception\Exception;
use Terablaze\EventDispatcher\Dispatcher;
use Terablaze\Filesystem\Files;
use Terablaze\Support\ArrayMethods;
use Terablaze\Support\StringMethods;

class Migrator
{
    /**
     * The container instance
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * The event dispatcher instance.
     *
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * The migration repository implementation.
     *
     * @var MigrationRepository
     */
    protected $repository;

    /**
     * The name of the default connection.
     *
     * @var string
     */
    protected $connectionName;

    /**
     * The paths to all of the migration files.
     *
     * @var array
     */
    protected $paths = [];

    /**
     * @var Files
     */
    protected $files;

    /**
     * The output interface implementation.
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * Migrator constructor.
     *
     * @param ContainerInterface $container
     * @param MigrationRepository $repository
     * @param Files $files
     * @param Dispatcher|null $dispatcher
     */
    public function __construct(
        ContainerInterface $container,
        MigrationRepository $repository,
        Files $files,
        Dispatcher $dispatcher = null
    ) {
        $this->container = $container;
        $this->repository = $repository;
        $this->files = $files;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Run the pending migrations at a given path.
     *
     * @param  array|string  $paths
     * @param  array  $options
     * @return array
     */
    public function run($paths = [], array $options = [])
    {
        // Once we grab all of the migration files for the path, we will compare them
        // against the migrations that have already been run for this package then
        // run each of the outstanding migrations against a database connection.
        $files = $this->getMigrationFiles($paths);

        $this->requireFiles($migrations = $this->pendingMigrations(
            $files,
            $this->repository->getRan()
        ));

        // Once we have all these migrations that are outstanding we are ready to run
        // we will go ahead and run them "up". This will execute each migration as
        // an operation against a database. Then we'll return this list of them.
        $this->runPending($migrations, $options);

        return $migrations;
    }

    /**
     * Get the migration files that have not yet run.
     *
     * @param  array  $files
     * @param  array  $ran
     * @return array
     */
    protected function pendingMigrations($files, $ran)
    {
        return (new ArrayCollection($files))
                ->filter(function ($file) use ($ran) {
                    return !in_array($this->getMigrationName($file), $ran);
                })->all();
    }

    /**
     * Run an array of migrations.
     *
     * @param  array  $migrations
     * @param  array  $options
     * @return void
     */
    public function runPending(array $migrations, array $options = [])
    {
        // First we will just make sure that there are any migrations to run. If there
        // aren't, we will just make a note of it to the developer so they're aware
        // that all of the migrations have been run against this database system.
        if (count($migrations) === 0) {
            $this->fireMigrationEvent(new NoPendingMigrations('up'));

            $this->note('<info>Nothing to migrate.</info>');

            return;
        }

        // Next, we will get the next batch number for the migrations so we can insert
        // correct batch number in the database migrations repository when we store
        // each migration's execution. We will also extract a few of the options.
        $batch = $this->repository->getNextBatchNumber();

        $pretend = $options['pretend'] ?? false;

        $step = $options['step'] ?? false;

        $this->fireMigrationEvent(new MigrationsStarted());

        // Once we have the array of migrations, we will spin through them and run the
        // migrations "up" so the changes are made to the databases. We'll then log
        // that the migration was run so we don't repeat it next time we execute.
        foreach ($migrations as $file) {
            $this->runUp($file, $batch, $pretend);

            if ($step) {
                $batch++;
            }
        }

        $this->fireMigrationEvent(new MigrationsEnded());
    }

    /**
     * Run "up" a migration instance.
     *
     * @param  string  $file
     * @param  int  $batch
     * @param  bool  $pretend
     * @return void
     */
    protected function runUp($file, $batch, $pretend)
    {
        // First we will resolve a "real" instance of the migration class from this
        // migration file name. Once we have the instances we can run the actual
        // command such as "up" or "down", or we can just simulate the action.
        $migration = $this->resolve(
            $name = $this->getMigrationName($file)
        );

        if ($pretend) {
            $this->pretendToRun($migration, 'up');
            return;
        }

        $this->note("<comment>Migrating:</comment> {$name}");

        $startTime = microtime(true);

        $this->runMigration($migration, 'up');

        $runTime = number_format((microtime(true) - $startTime) * 1000, 2);

        // Once we have run a migrations class, we will log that it was run in this
        // repository so that we don't try to run it next time we do a migration
        // in the application. A migration repository keeps the migrate order.
        $this->repository->log($name, $batch);

        $this->note("<info>Migrated:</info>  {$name} ({$runTime}ms)");
    }

    /**
     * Rollback the last migration operation.
     *
     * @param  array|string  $paths
     * @param  array  $options
     * @return array
     */
    public function rollback($paths = [], array $options = [])
    {
        // We want to pull in the last batch of migrations that ran on the previous
        // migration operation. We'll then reverse those migrations and run each
        // of them "down" to reverse the last migration "operation" which ran.
        $migrations = $this->getMigrationsForRollback($options);

        if (count($migrations) === 0) {
            $this->fireMigrationEvent(new NoPendingMigrations('down'));

            $this->note('<info>Nothing to rollback.</info>');

            return [];
        }

        return $this->rollbackMigrations($migrations, $paths, $options);
    }

    /**
     * Get the migrations for a rollback operation.
     *
     * @param  array  $options
     * @return array
     */
    protected function getMigrationsForRollback(array $options)
    {
        if (($steps = $options['step'] ?? 0) > 0) {
            return $this->repository->getMigrations($steps);
        }

        return $this->repository->getLast();
    }

    /**
     * Rollback the given migrations.
     *
     * @param  array  $migrations
     * @param  array|string  $paths
     * @param  array  $options
     * @return array
     */
    protected function rollbackMigrations(array $migrations, $paths, array $options)
    {
        $rolledBack = [];

        $this->requireFiles($files = $this->getMigrationFiles($paths));

        $this->fireMigrationEvent(new MigrationsStarted());

        // Next we will run through all of the migrations and call the "down" method
        // which will reverse each migration in order. This getLast method on the
        // repository already returns these migration's names in reverse order.
        foreach ($migrations as $migration) {
            $migration = (object) $migration;

            if (! $file = ArrayMethods::get($files, $migration->name)) {
                $this->note("<fg=red>Migration not found:</> {$migration->name}");

                continue;
            }

            $rolledBack[] = $file;

            $this->runDown(
                $file,
                $migration,
                $options['pretend'] ?? false
            );
        }

        $this->fireMigrationEvent(new MigrationsEnded());

        return $rolledBack;
    }

    /**
     * Rolls all of the currently applied migrations back.
     *
     * @param  array|string  $paths
     * @param  bool  $pretend
     * @return array
     */
    public function reset($paths = [], $pretend = false)
    {
        // Next, we will reverse the migration list so we can run them back in the
        // correct order for resetting this database. This will allow us to get
        // the database back into its "empty" state ready for the migrations.
        $migrations = array_reverse($this->repository->getRan());

        if (count($migrations) === 0) {
            $this->note('<info>Nothing to rollback.</info>');

            return [];
        }

        return $this->resetMigrations($migrations, $paths, $pretend);
    }

    /**
     * Reset the given migrations.
     *
     * @param  array  $migrations
     * @param  array  $paths
     * @param  bool  $pretend
     * @return array
     */
    protected function resetMigrations(array $migrations, array $paths, $pretend = false)
    {
        // Since the getRan method that retrieves the migration name just gives us the
        // migration name, we will format the names into objects with the name as a
        // property on the objects so that we can pass it to the rollback method.
        $migrations = (new ArrayCollection($migrations))->map(function ($m) {
            return (object) ['migration' => $m];
        })->all();

        return $this->rollbackMigrations(
            $migrations,
            $paths,
            compact('pretend')
        );
    }

    /**
     * Run "down" a migration instance.
     *
     * @param  string  $file
     * @param  object  $migration
     * @param  bool  $pretend
     * @return void
     */
    protected function runDown($file, $migration, $pretend)
    {
        // First we will get the file name of the migration so we can resolve out an
        // instance of the migration. Once we get an instance we can either run a
        // pretend execution of the migration or we can run the real migration.
        $instance = $this->resolve(
            $name = $this->getMigrationName($file)
        );

        $this->note("<comment>Rolling back:</comment> {$name}");

        if ($pretend) {
            $this->pretendToRun($instance, 'down');
            return;
        }

        $startTime = microtime(true);

        $this->runMigration($instance, 'down');

        $runTime = number_format((microtime(true) - $startTime) * 1000, 2);

        // Once we have successfully run the migration "down" we will remove it from
        // the migration repository so it will be considered to have not been run
        // by the application then will be able to fire by any later operation.
        $this->repository->delete($migration);

        $this->note("<info>Rolled back:</info>  {$name} ({$runTime}ms)");
    }

    /**
     * Run a migration inside a transaction if the database supports it.
     *
     * @param  object|Migration  $migration
     * @param string $method
     * @return void
     */
    protected function runMigration($migration, string $method)
    {
        $callback = function () use ($migration, $method) {
            if (method_exists($migration, $method)) {
                $this->fireMigrationEvent(new MigrationStarted($migration, $method));

                $migration->{$method}();

                $this->fireMigrationEvent(new MigrationEnded($migration, $method));
            }
        };

        $callback();
    }

    /**
     * Pretend to run the migrations.
     *
     * @param  object|Migration  $migration
     * @param  string  $method
     * @return void
     */
    protected function pretendToRun($migration, $method)
    {
        foreach ($this->getQueries($migration, $method) as $query) {
            $name = get_class($migration);

            $this->note("<info>{$name}:</info> {$query['query']}");
        }
    }

    /**
     * Get all of the queries that would be run for a migration.
     *
     * @param  Migration  $migration
     * @param  string  $method
     * @return array
     */
    protected function getQueries($migration, $method)
    {
        // Now that we have the connections we can resolve it and pretend to run the
        // queries against the database returning the array of raw SQL statements
        // that would get fired against the database system for this migration.
        $db = $this->resolveConnection(
            $migration->getConnectionName()
        );

        return $db->pretend(function () use ($migration, $method) {
            if (method_exists($migration, $method)) {
                $migration->{$method}();
            }
        });
    }

    /**
     * Resolve a migration instance from a file.
     *
     * @param  string  $file
     * @return object
     */
    public function resolve($file)
    {
        $class = StringMethods::studly(implode('_', array_slice(explode('_', $file), 4)));

        return new $class();
    }

    /**
     * Get all of the migration files in a given path.
     *
     * @param  string|array  $paths
     * @return array
     */
    public function getMigrationFiles($paths): array
    {
        $paths = ArrayMethods::wrap($paths);
        $migrationFiles = [];
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $subMigrationFiles = $this->files->glob($path . '/*_*.php');
            if (count($subMigrationFiles) < 1) {
                continue;
            }
            foreach ($subMigrationFiles as $subMigrationFile) {
                $migrationFiles[$this->getMigrationName($subMigrationFile)] = $subMigrationFile;
            }
        }

        ksort($migrationFiles);
        return $migrationFiles;
    }

    /**
     * Get all of the migration paths.
     *
     * @return array
     */
    public function getMigrationPaths()
    {
        $paths[] = $this->getAppMigrationPath();
        foreach (kernel()->getParcels() as $parcel) {
            if ($parcel instanceof ParcelInterface) {
                $paths[] = $parcel->getPath() . DIRECTORY_SEPARATOR
                    . "database" . DIRECTORY_SEPARATOR . "migrations";
            }
        }
        return $paths;
    }


    /**
     * Get the path to the migration directory.
     *
     * @return string
     */
    public function getAppMigrationPath()
    {
        return kernel()->getProjectDir() . DIRECTORY_SEPARATOR
            . "database" . DIRECTORY_SEPARATOR . "migrations";
    }

    /**
     * Require in all the migration files in a given path.
     *
     * @param  array  $files
     * @return void
     */
    public function requireFiles(array $files)
    {
        foreach ($files as $file) {
            $this->files->requireOnce($file);
        }
    }

    /**
     * Get the name of the migration.
     *
     * @param  string  $path
     * @return string
     */
    public function getMigrationName($path)
    {
        return str_replace('.php', '', basename($path));
    }

    /**
     * Register a custom migration path.
     *
     * @param  string  $path
     * @return void
     */
    public function path($path)
    {
        $this->paths = array_unique(array_merge($this->paths, [$path]));
    }

    /**
     * Get all of the custom migration paths.
     *
     * @return array
     */
    public function paths()
    {
        return $this->paths;
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connectionName;
    }

    /**
     * Execute the given callback using the given connection as the default connection.
     *
     * @param  string  $name
     * @param  callable  $callback
     * @return mixed
     */
    public function usingConnection($name, callable $callback)
    {
        $previousConnection = getConfig('database.default');

        $this->setConnectionName($name);

        return tap($callback(), function () use ($previousConnection) {
            $this->setConnectionName($previousConnection);
        });
    }

    /**
     * Set the default connection name.
     *
     * @param  string  $name
     * @return void
     */
    public function setConnectionName($name)
    {
        $this->connectionName = $name;
    }

    /**
     * Resolve the database connection instance.
     *
     * @param  string  $connection
     * @return ConnectionInterface
     */
    public function resolveConnection($connection = "")
    {
        if (!empty($connection)) {
            return $this->container->get("database.connection." . $connection);
        }
        return $this->repository->getConnection();
    }

    /**
     * Get the migration repository instance.
     *
     * @return MigrationRepository
     */
    public function getRepository(): MigrationRepository
    {
        return $this->repository;
    }

    /**
     * Determine if the migration repository exists.
     *
     * @return bool
     */
    public function repositoryExists(): bool
    {
        return $this->repository->repositoryExists();
    }

    /**
     * Determine if any migrations have been run.
     *
     * @return bool
     */
    public function hasRunAnyMigrations()
    {
        return $this->repositoryExists() && count($this->repository->getRan()) > 0;
    }

    /**
     * Delete the migration repository data store.
     *
     * @return void
     */
    public function deleteRepository()
    {
        $this->repository->deleteRepository();
    }

    /**
     * Get the file system instance.
     *
     * @return Files
     */
    public function getFilesystem(): Files
    {
        return $this->files;
    }

    /**
     * Set the output implementation that should be used by the console.
     *
     * @param OutputInterface $output
     * @return $this
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Write a note to the console's output.
     *
     * @param  string  $message
     * @return void
     */
    protected function note($message)
    {
        if ($this->output) {
            $this->output->writeln($message);
        }
    }

    /**
     * Fire the given event for the migration.
     *
     * @param object $event
     * @return object
     * @throws Exception
     */
    public function fireMigrationEvent(object $event): object
    {
        if ($this->dispatcher) {
            return $this->dispatcher->dispatch($event);
        }
        throw new Exception('EventDispatcher not initialized');
    }
}

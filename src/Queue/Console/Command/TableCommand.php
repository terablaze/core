<?php

namespace Terablaze\Queue\Console\Command;

use Terablaze\Console\Command;
use Terablaze\Database\Migrations\MigrationCreator;
use Terablaze\Filesystem\Files;
use Terablaze\Support\Composer;
use Terablaze\Support\Helpers;
use Terablaze\Support\StringMethods;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:table')]
class TableCommand extends Command
{
    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     */
    protected static $defaultName = 'queue:table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Create a migration for the queue jobs database table';

    protected MigrationCreator $migrationCreator;

    protected Files $files;

    protected Composer $composer;

    /**
     * Create a new queue job table command instance.
     *
     * @MigrationCreator $migrationCreator
     * @param Files $files
     * @param Composer $composer
     * @return void
     */
    public function __construct(MigrationCreator $migrationCreator, Files $files, Composer $composer)
    {
        parent::__construct();

        $this->migrationCreator = $migrationCreator;
        $this->files = $files;
        $this->composer = $composer;
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Create a migration for the queue jobs database table')
            ->setHelp('This command creates a migration for the queue jobs database table');
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $table = $this->kernel->getConfig()->get('queue.connections.database.table', 'jobs');

        $this->replaceMigration(
            $this->createBaseMigration($table), $table, StringMethods::studly($table)
        );

        $this->io->info('Jobs migration created successfully.');

        $this->composer->dumpAutoloads();

        return self::SUCCESS;
    }

    /**
     * Create a base migration file for the table.
     *
     * @param string $table
     * @return string
     */
    protected function createBaseMigration($table = 'jobs')
    {
        return $this->migrationCreator->create(
            'create_' . $table . '_table', Helpers::baseDir('database' . DIRECTORY_SEPARATOR . 'migrations')
        );
    }

    /**
     * Replace the generated migration with the job table stub.
     *
     * @param string $path
     * @param string $table
     * @return void
     */
    protected function replaceMigration($path, $table, $tableClassName)
    {
        $stub = str_replace(
            ['{{ table }}', '{{ tableClassName }}'],
            [$table, $tableClassName],
            $this->files->get(dirname(__DIR__) . '/stubs/jobs.stub')
        );

        $this->files->put($path, $stub);
    }
}

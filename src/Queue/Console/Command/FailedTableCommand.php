<?php

namespace Terablaze\Queue\Console\Command;

use Terablaze\Console\Command;
use Terablaze\Database\Migrations\MigrationCreator;
use Terablaze\Filesystem\Files;
use Terablaze\Support\Composer;
use Terablaze\Support\StringMethods;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue:failed-table')]
class FailedTableCommand extends Command
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
    protected static $defaultName = 'queue:failed-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Create a migration for the failed queue jobs database table';

    protected MigrationCreator $migrationCreator;

    protected Files $files;

    protected Composer $composer;

    /**
     * Create a new failed queue jobs table command instance.
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

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $table = $this->kernel->getConfig()->get('queue.failed.table', 'failed_jobs');

        $this->replaceMigration(
            $this->createBaseMigration($table), $table, StringMethods::studly($table)
        );

        $this->io->info('Migration created successfully.');

        $this->composer->dumpAutoloads();

        return self::SUCCESS;
    }

    /**
     * Create a base migration file for the table.
     *
     * @param  string  $table
     * @return string
     */
    protected function createBaseMigration($table = 'failed_jobs')
    {
        return $this->migrationCreator->create(
            'create_' . $table . '_table', baseDir('database' . DIRECTORY_SEPARATOR . 'migrations')
        );
    }

    /**
     * Replace the generated migration with the failed job table stub.
     *
     * @param  string  $path
     * @param  string  $table
     * @return void
     */
    protected function replaceMigration($path, $table, $tableClassName)
    {
        $stub = str_replace(
            ['{{ table }}', '{{ tableClassName }}'],
            [$table, $tableClassName],
            $this->files->get(__DIR__ . '/stubs/failed_jobs.stub')
        );

        $this->files->put($path, $stub);
    }
}

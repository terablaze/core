<?php

namespace Terablaze\Notifications\Console\Commands;

use Terablaze\Console\Command;
use Terablaze\Database\Migrations\MigrationCreator;
use Terablaze\Filesystem\Files;
use Terablaze\Support\Composer;
use Symfony\Component\Console\Attribute\AsCommand;
use Terablaze\Support\Helpers;

#[AsCommand(name: 'notifications:table')]
class NotificationTableCommand extends Command
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
    protected static $defaultName = 'notifications:table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Create a migration for the notifications table';

    /**
     * Create a new notifications table command instance.
     *
     * @param  \Terablaze\Filesystem\Files  $files
     * @param  \Terablaze\Support\Composer  $composer
     * @return void
     */
    public function __construct(protected MigrationCreator $migrationCreator, protected Files $files, protected Composer $composer)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $fullPath = $this->createBaseMigration();

        $this->files->put($fullPath, $this->files->get(dirname(__DIR__).'/stubs/notifications.stub'));

        $this->io->info('Migration created successfully.');

        $this->composer->dumpAutoloads();
    }

    /**
     * Create a base migration file for the notifications.
     *
     * @return string
     */
    protected function createBaseMigration()
    {
        $name = 'create_notifications_table';

        $path = Helpers::baseDir('database' . DIRECTORY_SEPARATOR . 'migrations');

        return $this->migrationCreator->create($name, $path);
    }
}

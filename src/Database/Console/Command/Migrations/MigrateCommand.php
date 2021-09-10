<?php

namespace TeraBlaze\Database\Console\Command\Migrations;

use TeraBlaze\Collection\ArrayCollection;
use TeraBlaze\Core\Console\Command;
use TeraBlaze\Database\Connection\ConnectionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TeraBlaze\Database\Migrations\Migration;

class MigrateCommand extends BaseCommand
{
    protected static $defaultName = 'migrate';

    protected function configure()
    {
        $this
            ->setDescription('Migrates the database')
            ->addOption('fresh', null, InputOption::VALUE_NONE, 'Delete all tables before running the migrations')
            ->addOption('conn', 'c', InputOption::VALUE_OPTIONAL, 'Sets the connection to use for migration')
            ->setHelp('This command looks for all migration files and runs them');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $paths = $this->getMigrations();

        if (count($paths) < 1) {
            $output->writeln('No migrations found');
            return Command::SUCCESS;
        }

        $connection = $this->connection(
            $input->getOption('conn') ?
                "database.connection." . $input->getOption('conn') :
                ConnectionInterface::class
        );

        if ($input->getOption('fresh')) {
            $output->writeln('Dropping existing database tables');

            $connection->dropTables();
        }

        if (!$connection->hasTable($connection->getMigrationsTable())) {
            $output->writeln('<fg=yellow>Creating migrations table</>');
            $this->createMigrationsTable($connection);
            $output->writeln('<fg=green>Created migrations table</>');
        }

        $migrations = (new ArrayCollection($connection->query()->select('*')
            ->from($connection->getMigrationsTable())
            ->all()))->pluck('name');

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                continue;
            }
            if (in_array($path, $migrations->toArray())) {
                continue;
            }
            $file = pathinfo($path);
            $class = explode('_', $file['filename'])[1];

            require $path;

            $output->writeln("<fg=yellow>Migrating:</> " . $file['filename']);

            /** @var Migration $migration */
            $migration = new $class();
            if (!is_null($mConnectionName = $migration->getConnectionName())) {
                $mConnection = $this->connection("database.connection.$mConnectionName");
                $migration->up($mConnection);
            } else {
                $migration->up($connection);
            }

            $connection
                ->getQueryBuilder()
                ->insert($connection->getMigrationsTable())
                ->values(['name' => ":migrationId"])
                ->setParameter('migrationId', $path)
                ->execute();

            $output->writeln("<fg=green>Migrated:</> " . $file['filename']);
        }

        return Command::SUCCESS;
    }

    private function createMigrationsTable(ConnectionInterface $connection): void
    {

    }
}

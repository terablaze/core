<?php

namespace TeraBlaze\Ripana\Database\Console\Command\Migrations;

use TeraBlaze\Collection\ArrayCollection;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Console\Command;
use TeraBlaze\Core\Kernel\KernelInterface;
use TeraBlaze\Ripana\Database\Connection\ConnectionInterface;
use TeraBlaze\Ripana\Database\Connection\Connection;
use TeraBlaze\Ripana\Database\Connection\MysqlConnection;
use TeraBlaze\Ripana\Database\Connection\SqliteConnection;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends BaseCommand
{
    protected static $defaultName = 'migrate';

    protected function configure()
    {
        $this
            ->setDescription('Migrates the database')
            ->addOption('fresh', null, InputOption::VALUE_NONE, 'Delete all tables before running the migrations')
            ->setHelp('This command looks for all migration files and runs them');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
//        $current = getcwd();
//        $pattern = 'database/migrations/*.php';
//
//        $paths = glob("{$current}/{$pattern}");

        $paths = $this->getMigrations();

        if (count($paths) < 1) {
            $output->writeln('No migrations found');
            return Command::SUCCESS;
        }

        $connection = $this->connection();

        if ($input->getOption('fresh')) {
            $output->writeln('Dropping existing database tables');

            $connection->dropTables();
            $connection = $this->connection();
        }

        if (!$connection->hasTable('migrations')) {
            $output->writeln('Creating migrations table');
            $this->createMigrationsTable($connection);
        }

        $migrations = (new ArrayCollection($connection->query()->select('*')
            ->from('migrations')
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

            $output->writeln("Migrating: {$class}");

            $obj = new $class();
            $obj->migrate($connection);

            $connection
                ->query()
                ->insert('migrations')
                ->values(['name' => ":migrationId"])
                ->setParameter('migrationId', $path)
                ->execute();
        }

        return Command::SUCCESS;
    }

    private function connection(): Connection
    {
        $mysql = new MysqlConnection([
            'type' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            "username" => 'root',
            "password" => 'teraboxx',
            "database" => 'terablaze_core',
        ]);

        return $mysql;

        /** @var Container $container */
        $container = $this->getApplication()->getKernel()->getContainer();
        return $container->get(ConnectionInterface::class);
    }

    private function createMigrationsTable(Connection $connection)
    {
        $table = $connection->createTable('migrations');
        $table->id('id');
        $table->string('name');
        $table->execute();
    }
}

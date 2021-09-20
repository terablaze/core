<?php

namespace TeraBlaze\Database\Console\Command\Migrations;

use Symfony\Component\Console\Input\InputOption;
use TeraBlaze\Core\Console\Command;
use TeraBlaze\Database\Migrations\MigrationRepository;

class InstallCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'migrate:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Create the migration repository';

    /**
     * The repository instance.
     *
     * @var MigrationRepository
     */
    protected $repository;

    /**
     * Create a new migration install command instance.
     *
     * @param MigrationRepository $repository
     * @return void
     */
    public function __construct(MigrationRepository $repository)
    {
        $this->repository = $repository;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        if ($this->input->getOption('database')) {
            $connString = 'database.connection.' . $this->input->getOption('database');
            $this->repository->setConnection(container()->get($connString));
        }

        $this->io->writeln('<fg=yellow>Creating migrations table</>');
        $this->repository->createRepository();
        $this->io->writeln('<info>Migration table created successfully.</info>');

        return Command::SUCCESS;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['database', 'd', InputOption::VALUE_OPTIONAL, 'The database connection to use'],
        ];
    }
}

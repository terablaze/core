<?php

namespace TeraBlaze\Database\Console\Command\Migrations;

use ReflectionException;
use TeraBlaze\Core\Console\Command;
use TeraBlaze\Core\Console\ConfirmableTrait;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TeraBlaze\Database\Migrations\Migrator;

class MigrateCommand extends BaseCommand
{
    use ConfirmableTrait;

    protected static $defaultName = 'migrate';

    protected Migrator $migrator;

    /**
     * Create a new migration command instance.
     *
     * @return void
     * @throws ReflectionException
     */
    public function __construct(Migrator $migrator)
    {
        $this->migrator = $migrator;

        parent::__construct();
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Migrates the database')
            ->setHelp('This command looks for all migration files and runs them');
    }

    protected function handle(): int
    {
        if (!$this->confirmToProceed()) {
            return 1;
        }

        $this->prepareDatabase($this->output);

        $paths = $this->getMigrationPaths();

        if ($this->input->hasOption('database')) {
            $this->migrator->setConnectionName($this->input->getOption('database'));
        }

        $this->migrator->setOutput($this->output);

        $this->migrator->setOutput($this->output)->run($paths, [
            'pretend' => $this->input->getOption('pretend'),
            'step' => $this->input->getOption('step'),
        ]);

        if ($this->input->getOption('seed') && ! $this->input->getOption('pretend')) {
            $this->call('db:seed', ['--force' => true]);
        }

        return Command::SUCCESS;
    }

    /**
     * Prepare the migration database for running.
     *
     * @return void
     */
    protected function prepareDatabase(OutputInterface $output)
    {
        if (! $this->migrator->repositoryExists()) {
            $this->call('migrate:install', array_filter([
                '--database' => $this->input->getOption('database'),
            ]));
        }
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['database', 'd', InputOption::VALUE_OPTIONAL, 'Sets the database connection to use for migration'],

            ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production'],

            ['pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run'],

            ['step', null, InputOption::VALUE_NONE, 'Force the migrations to be run so they can be rolled back individually'],

            ['seed', null, InputOption::VALUE_NONE, 'Indicates if the seed task should be re-run'],
        ];
    }
}

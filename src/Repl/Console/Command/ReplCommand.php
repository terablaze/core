<?php

namespace Terablaze\Repl\Console\Command;

use Psy\Configuration;
use Psy\Shell;
use Psy\VersionUpdater\Checker;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Terablaze\Console\Command;
use Terablaze\Database\Migrations\MigrationCreator;
use Terablaze\Repl\ClassAliasAutoloader;
use Terablaze\Support\Composer;
use Terablaze\Support\StringMethods;

class ReplCommand extends Command
{
    /**
     * Blaze commands to include in the repl shell.
     *
     * @var array
     */
    protected $commandWhitelist = [
        'clear-compiled', 'down', 'env', 'inspire', 'migrate', 'migrate:install', 'optimize', 'up',
    ];
    /**
     * The console command signature.
     *
     * @var string
     */
    protected static $defaultName = 'repl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Interact with the application from the console';

    /**
     * The migration creator instance.
     *
     * @var MigrationCreator
     */
    protected $creator;

    /**
     * The Composer instance.
     *
     * @var Composer
     */
    protected $composer;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->getApplication()->setCatchExceptions(false);

        $config = Configuration::fromInput($this->input);
        $config->setUpdateCheck(Checker::NEVER);

        $config->getPresenter()->addCasters(
            $this->getCasters()
        );

        if ($this->getOption('execute')) {
            $config->setRawOutput(true);
        }

        $shell = new Shell($config);
        $shell->addCommands($this->getCommands());
        $shell->setIncludes($this->getArgument('include'));

        $path = $this->getKernel()->getProjectDir().DIRECTORY_SEPARATOR.'vendor';

        $path .= '/composer/autoload_classmap.php';

        $config = $this->getKernel()->getConfig();

        $loader = ClassAliasAutoloader::register(
            $shell, $path, $config->get('repl.alias', []), $config->get('repl.dont_alias', [])
        );

        if ($code = $this->getOption('execute')) {
            try {
                $shell->setOutput($this->output);
                $shell->execute($code);
            } finally {
                $loader->unregister();
            }

            return 0;
        }

        try {
            return $shell->run();
        } finally {
            $loader->unregister();
        }
    }

    /**
     * Get artisan commands to pass through to PsySH.
     *
     * @return array
     */
    protected function getCommands()
    {
        $commands = [];

        foreach ($this->getApplication()->all() as $name => $command) {
            if (in_array($name, $this->commandWhitelist)) {
                $commands[] = $command;
            }
        }

        $config = $this->getKernel()->getConfig();

        foreach ($config->get('repl.commands', []) as $command) {
            $commands[] = $this->getApplication()->add(
                $this->getKernel()->getContainer()->make($command)
            );
        }

        return $commands;
    }

    /**
     * Get an array of Terablaze tailored casters.
     *
     * @return array
     */
    protected function getCasters()
    {
        $casters = [
            'Terablaze\Collection\ArrayCollection' => 'Terablaze\Repl\ReplCaster::castCollection',
            'Terablaze\Support\HtmlString' => 'Terablaze\Repl\ReplCaster::castHtmlString',
            'Terablaze\Support\Stringable' => 'Terablaze\Repl\ReplCaster::castStringable',
        ];

        if (class_exists('Terablaze\Database\ORM\Model')) {
//            $casters['Terablaze\Database\ORM\Model'] = 'Terablaze\Repl\ReplCaster::castModel';
        }

        if (class_exists('Terablaze\Core\Kernel\Kernel')) {
            $casters['Terablaze\Core\Kernel\Kernel'] = 'Terablaze\Repl\ReplCaster::castKernel';
        }

        $config = $this->getKernel()->getConfig();

        return array_merge($casters, (array) $config->get('repl.casters', []));
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['include', InputArgument::IS_ARRAY, 'Include file(s) before starting Repl'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['execute', null, InputOption::VALUE_OPTIONAL, 'Execute the given code using Repl'],
        ];
    }
}

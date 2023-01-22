<?php

namespace Terablaze\Console;

use Exception;
use ReflectionException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Terablaze\Container\Container;
use Terablaze\Core\Kernel\KernelInterface;

abstract class Command extends SymfonyCommand
{
    use CallsCommands;

    protected Container $container;

    protected KernelInterface $kernel;

    /** @var InputInterface $input */
    protected $input;

    /** @var OutputInterface $output */
    protected $output;

    /** @var SymfonyStyle $io */
    protected $io;

    /**
     * An instance of Symfony's console terminal.
     */
    protected Terminal $terminal;

    /**
     * @throws ReflectionException
     */
    public function __construct(string $name = null, Terminal$terminal = null)
    {
        parent::__construct($name);
        $this->container = Container::getContainer();
        $this->kernel = kernel();
        $this->terminal = $terminal ?? new Terminal();
    }

    public function getKernel(): KernelInterface
    {
        return $this->kernel;
    }

    protected function confirm(string $questionString): bool
    {
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($questionString, false);

        if (!$helper->ask($this->input, $this->output, $question)) {
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    protected function configure()
    {
        foreach ($this->getArguments() as $argument) {
            $this->addArgument(...$argument);
        }
        foreach ($this->getOptions() as $option) {
            $this->addOption(...$option);
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);
        return $this->container->call([$this, 'handle']) ?? self::SUCCESS;
    }

    /**
     * @throws Exception
     */
    protected function call(string $command, array $arguments = [], bool $supressOutput = false): int
    {
        $command = $this->getApplication()->find($command);

        $commandInput = new ArrayInput($arguments);
        $output = $supressOutput ? new NullOutput() : $this->output;
        return $command->run($commandInput, $output);
    }

    /**
     * @return InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Resolve the console command instance for the given command.
     *
     * @param  \Symfony\Component\Console\Command\Command|string  $command
     * @return \Symfony\Component\Console\Command\Command
     */
    protected function resolveCommand($command)
    {
        if (! class_exists($command)) {
            return $this->getApplication()->find($command);
        }

        $command = $this->container->make($command);

        if ($command instanceof SymfonyCommand) {
            $command->setApplication($this->getApplication());
        }

        return $command;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }

    /**
     * Get a console command argument.
     * @param string $argument
     * @return mixed
     */
    protected function getArgument(string $argument)
    {
        return $this->getInput()->getArgument($argument);
    }

    /**
     * Get a console command option.
     * @param string $option
     * @return mixed
     */
    protected function getOption(string $option)
    {
        return $this->getInput()->getOption($option);
    }
}

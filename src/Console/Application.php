<?php

namespace TeraBlaze\Console;

use TeraBlaze\Console\Controller\AbstractController;
use TeraBlaze\Console\Output\ConsoleOutput;
use TeraBlaze\Console\Registry\CommandRegistry;

class Application
{
    protected $printer;

    protected $commandRegistry;

    public function __construct()
    {
        $this->printer = new ConsoleOutput();
        $this->commandRegistry = new CommandRegistry();
    }

    public function getPrinter()
    {
        return $this->printer;
    }

    public function registerController($name, AbstractController $controller)
    {
        $this->commandRegistry->registerController($name, $controller);
    }

    public function registerCommand($name, $callable)
    {
        $this->commandRegistry->registerCommand($name, $callable);
    }

    public function runCommand(array $argv = [], $defaultCommand = 'help')
    {
        $commandName = $defaultCommand;

        if (isset($argv[1])) {
            $commandName = $argv[1];
        }

        try {
            call_user_func($this->commandRegistry->getCallable($commandName), $argv);
        } catch (\Exception $e) {
            $this->getPrinter()->display("ERROR: " . $e->getMessage());
            exit;
        }
    }
}

<?php

namespace Terablaze\Console\Concerns;

use Terablaze\Support\StringMethods;
use Symfony\Component\Console\Input\InputOption;

trait CreatesMatchingTest
{
    /**
     * Add the standard command options for generating matching tests.
     *
     * @return void
     */
    protected function addTestOptions()
    {
        foreach (['test' => 'PHPUnit', 'pest' => 'Pest'] as $option => $name) {
            $this->getDefinition()->addOption(new InputOption(
                $option,
                null,
                InputOption::VALUE_NONE,
                "Generate an accompanying {$name} test for the {$this->type}"
            ));
        }
    }

    /**
     * Create the matching test case if requested.
     *
     * @param  string  $path
     * @return bool
     */
    protected function handleTestCreation($path)
    {
        if (! $this->getInput()->getOption('test') && ! $this->getInput()->getOption('pest')) {
            return false;
        }

        return $this->callSilent('make:test', [
            'name' => StringMethods::of($path)->after($this->getKernel()->getProjectDir())->beforeLast('.php')->append('Test')->replace('\\', '/'),
            '--pest' => $this->getInput()->getOption('pest'),
        ]) == 0;
    }
}

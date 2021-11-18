<?php

namespace TeraBlaze\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait ConfirmableTrait
{

    /** @var InputInterface $input */
    protected $input;

    /** @var OutputInterface $output */
    protected $output;
    /**
     * Confirm before proceeding with the action.
     *
     * This method only asks for confirmation in production.
     *
     * @param  string  $warning
     * @param  \Closure|bool|null  $callback
     * @return bool
     */
    public function confirmToProceed($warning = 'Application In Production!', $callback = null)
    {
        $callback = is_null($callback) ? $this->getDefaultConfirmCallback() : $callback;

        $shouldConfirm = value($callback);

        if ($shouldConfirm) {
            if ($this->input->hasOption('force') && $this->input->getOption('force')) {
                return true;
            }

            $this->output->writeln("<comment>$warning</comment>");

            $confirmed = $this->confirm('Do you really wish to run this command?');

            if (! $confirmed) {
                $this->output->writeln('<comment>Command Canceled!</comment>');

                return false;
            }
        }

        return true;
    }

    /**
     * Get the default confirmation callback.
     *
     * @return \Closure
     */
    protected function getDefaultConfirmCallback()
    {
        return function () {
            return $this->getKernel()->getEnvironment() === 'production'
                || $this->getKernel()->getEnvironment() === 'prod'
                || $this->getKernel()->getEnvironment() === 'live'
                || $this->getKernel()->getEnvironment() === 'master';
        };
    }
}

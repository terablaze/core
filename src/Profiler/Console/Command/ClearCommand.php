<?php

namespace Terablaze\Profiler\Console\Command;

use Terablaze\Console\Command;
use Terablaze\Profiler\DebugBar\TerablazeDebugbar;

class ClearCommand extends Command
{
    protected static $defaultName = 'debugbar:clear';
    protected static $defaultDescription = 'Clear the Debugbar Storage';

    protected $debugbar;

    public function __construct(TerablazeDebugbar $debugbar)
    {
        $this->debugbar = $debugbar;

        parent::__construct();
    }

    public function handle()
    {
        $this->debugbar->boot();

        if ($storage = $this->debugbar->getStorage()) {
            try
            {
                $storage->clear();
            } catch(\InvalidArgumentException $e) {
                // hide InvalidArgumentException if storage location does not exist
                if(strpos($e->getMessage(), 'does not exist') === false) {
                    throw $e;
                }
            }
            $this->io->info('Debugbar Storage cleared!');
        } else {
            $this->io->error('No Debugbar Storage found..');
        }
    }
}

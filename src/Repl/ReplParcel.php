<?php

namespace Terablaze\Repl;

use DebugBar\DebugBarException;
use ReflectionException;
use Terablaze\Config\ConfigInterface;
use Terablaze\Config\Exception\InvalidContextException;
use Terablaze\Console\Application;
use Terablaze\Core\Parcel\Parcel;
use Terablaze\Core\Parcel\ParcelInterface;

use Terablaze\Repl\Console\Command\ReplCommand;

class ReplParcel extends Parcel implements ParcelInterface
{
    public function boot(): void
    {
        if ($this->getKernel()->inConsole()) {
            return;
        }
        $this->loadConfig("repl");
    }

    public function registerCommands(Application $application)
    {
        $application->add($this->container->make(ReplCommand::class));
    }
}

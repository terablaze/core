<?php

namespace TeraBlaze\Profiler\Debugbar;

use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DataCollector\RequestDataCollector;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;

class TeraBlazeDebugbar extends DebugBar
{
    public $container;
    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container ?: Container::getContainer();

        $this->addCollector(new PhpInfoCollector());
        $this->addCollector(new MessagesCollector());
//        $this->addCollector(new RequestDataCollector());
        $this->addCollector(new TimeDataCollector());
        $this->addCollector(new MemoryCollector());
        $this->addCollector(new ExceptionsCollector());
    }
}
<?php

namespace TeraBlaze\Core\Console;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Kernel\KernelInterface;

class Command extends SymfonyCommand
{
    private Container $container;

    /**
     * @var KernelInterface
     */
    private $kernel;

    public function __construct(string $name = null)
    {
        parent::__construct($name);
        $this->container = Container::getContainer();
        $this->kernel = $this->container->get(KernelInterface::class);
    }

    public function getKernel(): KernelInterface
    {
        return $this->kernel;
    }
}

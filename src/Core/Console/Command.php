<?php

namespace TeraBlaze\Core\Console;

use ReflectionException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Kernel\KernelInterface;

class Command extends SymfonyCommand
{
    protected Container $container;

    protected KernelInterface $kernel;

    /**
     * @throws ReflectionException
     */
    public function __construct(string $name = null)
    {
        parent::__construct($name);
        $this->container = Container::getContainer();
        $this->kernel = kernel();
    }

    public function getKernel(): KernelInterface
    {
        return $this->kernel;
    }
}

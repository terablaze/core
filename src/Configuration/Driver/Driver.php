<?php

namespace TeraBlaze\Configuration\Driver;

use TeraBlaze\Base as Base;
use TeraBlaze\Configuration\Exception as Exception;
use TeraBlaze\Configuration\Exception\ArgumentException;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Kernel\KernelInterface;

/**
 * Class Driver
 * @package TeraBlaze\Configuration
 */
abstract class Driver
{
    protected KernelInterface $kernel;

    /**
     * Base constructor.
     * @param array $options
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    protected function throwConfigFileDoesNotExistException(string $envFile, string $file)
    {
        throw new ArgumentException("Configuration files: {$envFile} and {$file} do not exist");
    }
}

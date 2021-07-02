<?php

namespace TeraBlaze\Config\Driver;

use TeraBlaze\ArrayMethods as ArrayMethods;
use TeraBlaze\Config\Exception\ArgumentException;
use TeraBlaze\Config\Exception\SyntaxException;
use TeraBlaze\Core\Kernel\KernelInterface;

/**
 * Class Driver
 * @package TeraBlaze\Configuration
 */
abstract class Driver
{
    protected KernelInterface $kernel;

    private string $fileExtension;

    /**
     * Base constructor.
     */
    public function __construct(KernelInterface $kernel, string $fileExtension)
    {
        $this->kernel = $kernel;
        $this->fileExtension = $fileExtension;
    }

    /**
     * @param string $path
     * @return array<string, mixed>
     * @throws ArgumentException
     * @throws SyntaxException
     */
    abstract public function parseArray(string $path): array;

    /**
     * @param string $path
     * @return object|null
     * @throws ArgumentException
     * @throws SyntaxException
     */
    public function parse(string $path): ?object
    {
        return ArrayMethods::toObject($this->parseArray($path));
    }

    /**
     * @param string $path
     * @return array<string, mixed>
     * @throws ArgumentException
     */
    protected function getConfigFromFile(string $path): array
    {
        if (empty($path)) {
            throw new ArgumentException("\$path argument is not valid");
        }
        $projectDir = $this->kernel->getProjectDir();
        $environment = $this->kernel->getEnvironment();
        $envConfigFile = "{$projectDir}/config/{$environment}/{$path}.{$this->fileExtension}";
        $configFile = "{$projectDir}/config/{$path}.{$this->fileExtension}";
        if (!file_exists($envConfigFile) && !file_exists($configFile)) {
            $this->throwConfigFileDoesNotExistException($path);
        }
        if (file_exists($envConfigFile)) {
            return include($envConfigFile);
        }
        return include($configFile);
    }

    /**
     * @throws ArgumentException
     */
    protected function throwConfigFileDoesNotExistException(string $file): void
    {
        throw new ArgumentException("Configuration file {$file} cannot be found");
    }
}

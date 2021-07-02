<?php

namespace TeraBlaze\Config;

use TeraBlaze\Config\Driver\DriverInterface;
use TeraBlaze\Config\Driver\Ini;
use TeraBlaze\Config\Driver\PHPArray;
use TeraBlaze\Config\Exception as Exception;
use TeraBlaze\Core\Kernel\KernelInterface;
use TeraBlaze\Events\Events;

/**
 * Class Configuration
 * @package TeraBlaze
 *
 * loads the configuration to be used by the entire application
 */
class Configuration
{
    protected $options;

    /** @var mixed $loadedConfigs */
    protected $loadedConfigs = [];

    protected KernelInterface $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

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
     * @return DriverInterface|Ini|PHPArray
     * @throws Exception\ArgumentException
     */
    public function initialize()
    {
        Events::fire("terablaze.configuration.initialize.before", array($this->type, $this->options));

        if (!$this->type) {
            throw new Exception\ArgumentException("Configuration type not supplied");
        }

        Events::fire("terablaze.configuration.initialize.after", array($this->type, $this->options));

        switch ($this->type) {
            case "ini":
            {
                return new Driver\Ini($this->kernel, 'ini');
            }
            case "PhpArray":
            case "PHPArray":
            case "php_array":
            case "phparray":
            {
                return new Driver\PHPArray($this->kernel, 'php');
            }
            default:
            {
                throw new Exception\ArgumentException("Invalid type");
            }
        }
    }
}

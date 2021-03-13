<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 1/30/2017
 * Time: 3:36 PM
 */

namespace TeraBlaze\Configuration\Driver;

use TeraBlaze\ArrayMethods as ArrayMethods;
use TeraBlaze\Configuration\Driver\Driver;
use TeraBlaze\Configuration\Exception\Argument;

/**
 * Class PHPArray
 * @package TeraBlaze\Configuration\Driver
 *
 * handles loading of php array configuration files
 * and passing it's values
 *
 */
class PHPArray extends Driver implements DriverInterface
{
    /**
     * @param $path
     * @return \stdClass
     *
     * includes the php array configuration files
     * and creates an object from it's key/value pairs
     *
     */
    public function parse(string $path): ?object
    {
        $config = $this->getConfigFromFile($path);
        $configObject = ArrayMethods::toObject($config);
        
        return $configObject;
    }
    /**
     * @param $path
     * @return \stdClass
     *
     * includes the php array configuration files
     * and creates an array from it's key/value pairs
     *
     */
    public function parseArray(string $path): ?array
    {
        return $this->getConfigFromFile($path);
    }

    private function getConfigFromFile(string $path): array
    {
        if (empty($path)) {
            throw new Argument("\$path argument is not valid");
        }
        $projectDir = $this->kernel->getProjectDir();
        $environment = $this->kernel->getEnvironment();
        $configFile = "{$projectDir}/config/{$path}.php";
        $envConfigFile = "{$projectDir}/config/{$environment}/{$path}.php";
        if (file_exists($envConfigFile)) {
            return include($envConfigFile);
        }
        if (file_exists($configFile)) {
            return include($configFile);
        } 
        $this->throwConfigFileDoesNotExistException($envConfigFile, $configFile);
    }
}

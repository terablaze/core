<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 1/30/2017
 * Time: 3:03 PM
 */

namespace TeraBlaze\Configuration\Driver;

use TeraBlaze\ArrayMethods as ArrayMethods;
use TeraBlaze\Configuration\Driver\Driver;
use TeraBlaze\Configuration\Exception as Exception;
use TeraBlaze\Configuration\Exception\Argument;

/**
 * Class Ini
 * @package TeraBlaze\Configuration\Driver
 *
 * handles loading of .ini configuration files
 * and passing it's values
 *
 */
class Ini extends Driver implements DriverInterface
{
    /**
     * @param $path
     * @return mixed
     * @throws Exception\Argument
     * @throws Exception\Syntax
     *
     * includes the .ini configuration files
     * and creates an object from it's key/value pairs
     *
     */
    public function parse(string $path): ?object
    {
        if (empty($path)) {
            throw new Exception\Argument("\$path argument is not valid");
        }

        if (!isset($this->_parsed[$path])) {
            $config = array();

            ob_start();
            $this->getConfigFromFile($path);
            $string = ob_get_contents();
            ob_end_clean();

            $pairs = parse_ini_string($string);

            if ($pairs == false) {
                throw new Exception\Syntax("Could not parse configuration file: {$path}");
            }

            foreach ($pairs as $key => $value) {
                $config = $this->_pair($config, $key, $value);
            }

            $this->_parsed[$path] = ArrayMethods::toObject($config);
        }

        return $this->_parsed[$path];
    }

    /**
     * @param $path
     * @return array
     * @throws Exception\Argument
     * @throws Exception\Syntax
     *
     * includes the .ini configuration files
     * and creates an object from it's key/value pairs
     *
     */
    public function parseArray(string $path): ?array
    {
        if (empty($path)) {
            throw new Exception\Argument("\$path argument is not valid");
        }

        if (!isset($this->_parsed[$path])) {
            $config = array();

            ob_start();
            $this->getConfigFromFile($path);
            $string = ob_get_contents();
            ob_end_clean();

            $pairs = parse_ini_string($string);

            if ($pairs == false) {
                throw new Exception\Syntax("Could not parse configuration file: {$path}");
            }

            foreach ($pairs as $key => $value) {
                $config = $this->_pair($config, $key, $value);
            }

            $this->_parsed[$path] = ArrayMethods::clean($config);
        }

        return $this->_parsed[$path];
    }

    /**
     * @param $config
     * @param $key
     * @param $value
     * @return mixed
     */
    protected function _pair($config, $key, $value)
    {
        if (strstr($key, ".")) {
            $parts = explode(".", $key, 2);

            if (empty($config[$parts[0]])) {
                $config[$parts[0]] = array();
            }

            $config[$parts[0]] = $this->_pair($config[$parts[0]], $parts[1], $value);
        } else {
            $config[$key] = $value;
        }

        return $config;
    }

    private function getConfigFromFile(string $path): array
    {
        if (empty($path)) {
            throw new Argument("\$path argument is not valid");
        }
        $projectDir = $this->kernel->getProjectDir();
        $environment = $this->kernel->getEnvironment();
        $envConfigFile = "{$projectDir}/config/{$environment}/{$path}.ini";
        if (file_exists($envConfigFile)) {
            return include($envConfigFile);
        }
        $configFile = "{$projectDir}/config/{$path}.ini";
        if (file_exists($configFile)) {
            return include($configFile);
        }
        $this->throwConfigFileDoesNotExistException($envConfigFile, $configFile);
    }
}

<?php

namespace TeraBlaze\Config\Driver;

use TeraBlaze\ArrayMethods as ArrayMethods;
use TeraBlaze\Config\Driver\Driver;
use TeraBlaze\Config\Exception as Exception;
use TeraBlaze\Config\Exception\ArgumentException;

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
     * @param string $path
     * @return array<string, mixed>
     * @throws ArgumentException
     * @throws Exception\SyntaxException
     */
    public function parseArray(string $path): array
    {
        if (empty($path)) {
            throw new Exception\ArgumentException("\$path argument is not valid");
        }

        $config = [];

        ob_start();
        $this->getConfigFromFile($path);
        $string = ob_get_contents();
        ob_end_clean();

        $pairs = parse_ini_string($string);

        if ($pairs == false) {
            throw new Exception\SyntaxException("Could not parse configuration file: {$path}");
        }

        foreach ($pairs as $key => $value) {
            $config = $this->pair($config, $key, $value);
        }
        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @param string $key
     * @param mixed $value
     * @return array<string, mixed>
     */
    protected function pair(array $config, string $key, $value): array
    {
        if (strstr($key, ".")) {
            $parts = explode(".", $key, 2);

            if (empty($config[$parts[0]])) {
                $config[$parts[0]] = array();
            }

            $config[$parts[0]] = $this->pair($config[$parts[0]], $parts[1], $value);
        } else {
            $config[$key] = $value;
        }

        return $config;
    }
}

<?php

namespace TeraBlaze\Config\Driver;

use TeraBlaze\Config\Exception\ArgumentException;

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
     * @param string $path
     * @return array<string, mixed>
     * @throws ArgumentException
     */
    public function parseArray(string $path): array
    {
        $config = $this->getConfigFromFile($path);
        $this->kernel->getContainer()->registerConfig($path, $config);
        return $config;
    }
}

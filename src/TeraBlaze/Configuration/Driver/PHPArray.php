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
        if (empty($path)) {
            throw new Argument("\$path argument is not valid");
        }
        $configFile = $this->container->get('app.kernel')->getProjectDir() . "/{$path}.php";
        if (!file_exists($configFile)) {
            throw new Argument("Configuration file does not exist");
        }
        $config = include($configFile);
        
        return ArrayMethods::toObject($config);
    }
}

<?php

namespace TeraBlaze\Configuration\Driver;

use TeraBlaze\Base as Base;
use TeraBlaze\Configuration\Exception as Exception;
use TeraBlaze\Configuration\Exception\Argument;
use TeraBlaze\Container\Container;

/**
 * Class Driver
 * @package TeraBlaze\Configuration
 */
abstract class Driver
{
	protected $container;
	
	/**
	 * Base constructor.
	 * @param array $options
	 */
	public function __construct()
	{
		$this->container = Container::getContainer();
    }
    
    protected function throwConfigFileDoesNotExistException(string $file)
    {
        throw new Argument("Configuration file: {$file} does not exist");
    }
}

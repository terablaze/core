<?php
/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 1/30/2017
 * Time: 3:36 PM
 */

namespace TeraBlaze\Configuration\Driver;

use InvalidArgumentException;
use TeraBlaze\ArrayMethods as ArrayMethods;

/**
 * Class PHPArray
 * @package TeraBlaze\Configuration\Driver
 *
 * handles loading of php array configuration files
 * and passing it's values
 *
 */
class PHPArray extends Driver
{
	/**
	 * @param string $path
	 * @return \stdClass
	 *
	 * includes the php array configuration files
	 * and creates an object from it's key/value pairs
	 *
	 */
	public function parse(string $path)
	{
        if (empty($path))
        {
            throw new InvalidArgumentException("\$path argument is not valid");
        }
		$config = [];
		include("{$path}.php");
		return ArrayMethods::toObject($config);
	}
}
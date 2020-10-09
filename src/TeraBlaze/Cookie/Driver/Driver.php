<?php
/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/4/2017
 * Time: 9:19 AM
 */

namespace TeraBlaze\Cookie\Driver;

use TeraBlaze\Base as Base;
use TeraBlaze\Cookie\Exception as Exception;

abstract class Driver extends Base
{
	public function initialize()
	{
		return $this;
	}

	protected function _getExceptionForImplementation($method)
	{
		return new Exception\Implementation("{$method} method not implemented");
	}
}
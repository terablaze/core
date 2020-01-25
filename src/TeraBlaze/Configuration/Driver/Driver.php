<?php

namespace TeraBlaze\Configuration\Driver;

/**
 * Abstract Class Driver
 * @package TeraBlaze\Configuration\Driver
 */
abstract class Driver
{
    protected $_parsed = array();

    abstract protected function parse(string $path);
}

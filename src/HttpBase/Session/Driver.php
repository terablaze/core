<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/4/2017
 * Time: 9:19 AM
 */

namespace TeraBlaze\HttpBase\Session;

use TeraBlaze\Base as Base;

abstract class Driver extends Base
{
    abstract public function get($key, $default = null);
    abstract public function set($key, $value = null);
    abstract public function getFlash($key, $default = null);
    abstract public function setFlash($key, $value = null);
    abstract public function erase($key);
}

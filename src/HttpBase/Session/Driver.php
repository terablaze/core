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
    public abstract function get($key, $default = NULL);
    public abstract function set($key, $value = NULL);
    public abstract function getFlash($key, $default = NULL);
    public abstract function setFlash($key, $value = NULL);
    public abstract function erase($key);
}

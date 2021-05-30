<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 1/30/2017
 * Time: 4:14 PM
 */

namespace TeraBlaze\Cache\Driver;

use TeraBlaze\Base as Base;

abstract class Driver extends Base
{
    public function __construct(array $options = array())
    {
        parent::__construct($options);
        $this->connect();
    }
}

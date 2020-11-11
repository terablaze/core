<?php

namespace TeraBlaze\Ripana\Database;

use TeraBlaze\Base as Base;

abstract class Connector extends Base implements ConnectorInterface
{
    public function __construct(array $options = array())
    {
        parent::__construct($options);
        $this->connect();
    }
}

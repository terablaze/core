<?php

namespace TeraBlaze\Ripana\Database;

use TeraBlaze\Base as Base;

abstract class Connector extends Base implements ConnectorInterface
{
    /**
     * @readwrite
     */
    protected $_dateTimeMode = 'DATETIME';

    public function __construct(array $options = array())
    {
        parent::__construct($options);
        $this->connect();
    }

    public function getDateTimeMode(): string
    {
        return $this->_dateTimeMode;
    }
}

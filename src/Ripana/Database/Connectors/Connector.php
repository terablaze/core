<?php

namespace TeraBlaze\Ripana\Database\Connectors;

use TeraBlaze\Base as Base;
use TeraBlaze\Ripana\Logging\QueryLogger;

abstract class Connector extends Base implements ConnectorInterface
{
    /**
     * @readwrite
     */
    protected $_dateTimeMode = 'DATETIME';

    /** @var QueryLogger $queryLogger */
    protected $queryLogger;

    public function __construct(array $options = array())
    {
        parent::__construct($options);
        $this->queryLogger = new QueryLogger();
        $this->connect();
    }

    public function getDateTimeMode(): string
    {
        return $this->_dateTimeMode;
    }

    public function getQueryLogger(): QueryLogger
    {
        return $this->queryLogger;
    }
}

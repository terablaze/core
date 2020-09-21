<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/1/2017
 * Time: 4:39 AM
 */

namespace TeraBlaze\Ripana\Database\Connector;

use TeraBlaze\Base as Base;
use TeraBlaze\Ripana\Database\Exception as Exception;
use TeraBlaze\Ripana\Database\Query\Query;
use TeraBlaze\Ripana\ORM\Model;

abstract class Connector extends Base
{

    public function __construct(array $options = array())
    {
        parent::__construct($options);
        return $this->connect();
    }

    abstract protected function _isValidService();
    abstract public function connect();
    abstract public function disconnect();
    abstract public function query(): Query;
    abstract public function execute(string $sql);
    abstract public function escape(string $value);
    abstract public function getLastInsertId();
    abstract public function getAffectedRows();
    abstract public function getLastError();
    abstract public function buildSyncSQL($model);
    abstract public function sync($model);

    /**
     * @return $this
     */
    public function initialize()
    {
        return $this;
    }

    /**
     * @param $method
     * @return Exception\Implementation
     */
    protected function _getExceptionForImplementation($method)
    {
        return new Exception\Implementation("{$method} method not implemented");
    }
}

<?php
/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 1/30/2017
 * Time: 4:17 PM
 */

namespace TeraBlaze\Libraries\Cache\Driver;

use TeraBlaze\Libraries\Cache as Cache;
use TeraBlaze\Libraries\Cache\Exception as Exception;

class Memcache extends Driver
{
	protected $_service;

	/**
	 * @readwrite
	 */
	protected $_host;

	/**
	 * @readwrite
	 */
	protected $_port;

	/**
	 * @readwrite
	 */
	protected $_prefix;

	/**
	 * @readwrite
	 */
	protected $_duration;

	/**
	 * @readwrite
	 */
	protected $_isConnected = false;

	private $memcached_compressed = MEMCACHE_COMPRESSED;

	protected function _isValidService()
	{
		$isEmpty = empty($this->_service);
		$isInstance = $this->_service instanceof \Memcache;

		if ($this->isConnected && $isInstance && !$isEmpty)
		{
			return true;
		}

		return false;
	}

	// TODO: Add support for multiple servers

    /**
     * @return $this
     * @throws Exception\Service
     */
	public function connect()
	{
		try
		{
			$this->_service = new \Memcache();
			$this->_service->connect(
				$this->host,
				$this->port
			);
			$this->isConnected = true;
		}
		catch (\Exception $e)
		{
			throw new Exception\Service("Unable to connect to service");
		}

		return $this;
	}

	public function disconnect()
	{
		if ($this->_isValidService())
		{
			$this->_service->close();
			$this->isConnected = false;
		}

		return $this;
	}

    /**
     * @param $key
     * @param null $default
     * @return mixed|null
     * @throws Exception\Service
     */
	public function get($key, $default = null)
	{
		if (!$this->_isValidService())
		{
			throw new Exception\Service("Not connected to a valid service");
		}

		$value = $this->_service->get($this->prefix.$key, $this->memcached_compressed);

		if ($value)
		{
			return unserialize($value);
		}

		return $default;
	}

    /**
     * @param $key
     * @param $value
     * @param string $duration
     * @return $this
     * @throws Exception\Service
     */
	public function set($key, $value, $duration = "")
	{
		if (!$this->_isValidService())
		{
			throw new Exception\Service("Not connected to a valid service");
		}

		if(empty($duration)){
			$duration = $this->duration;
		}
		$this->_service->set($this->prefix.$key, serialize($value), $this->memcached_compressed, $duration);
		return $this;
	}

    /**
     * @param $key
     * @param $value
     * @param string $duration
     * @return $this
     * @throws Exception\Service
     */
	public function add($key, $value, $duration = "")
	{
		if (!$this->_isValidService())
		{
			throw new Exception\Service("Not connected to a valid service");
		}

		if(empty($duration)){
			$duration = $this->duration;
		}
		$this->_service->add($this->prefix.$key, serialize($value), $this->memcached_compressed, $duration);
		return $this;
	}

    /**
     * @param $key
     * @param $value
     * @param string $duration
     * @return $this
     * @throws Exception\Service
     */
	public function replace($key, $value, $duration = "")
	{
		if (!$this->_isValidService())
		{
			throw new Exception\Service("Not connected to a valid service");
		}

		if(empty($duration)){
			$duration = $this->duration;
		}
		$this->_service->replace($this->prefix.$key, serialize($value), $this->memcached_compressed, $duration);
		return $this;
	}

    /**
     * @param $key
     * @return $this
     * @throws Exception\Service
     */
	public function erase($key)
	{
		if (!$this->_isValidService())
		{
			throw new Exception\Service("Not connected to a valid service");
		}

		$this->_service->delete($this->prefix.$key);
		return $this;
	}
}
<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 1/30/2017
 * Time: 4:17 PM
 */

namespace TeraBlaze\Cache\Driver;

use TeraBlaze\Cache\Exception\Service as ServiceException;

class Memcached extends CacheDriver
{
    private $memcached_compressed = \Memcached::OPT_COMPRESSION;

    public function connect()
    {
        try {
            $this->_service = new \Memcached();

            $servers = [];
            $_servers = (array)$this->_servers;
            foreach ($_servers as $server) {
                $servers[] = [
                    $server->host,
                    $server->port
                ];
            }

            if (empty($servers)) {
                $this->_service->addServer(
                    $this->host,
                    $this->port
                );
            }

            if (is_array($servers) && !empty($servers)) {
                $this->_service->addServers(
                    $servers
                );
            }

            $this->_service->setOption(\Memcached::OPT_COMPRESSION, true);

            $this->isConnected = true;
        } catch (\Exception $e) {
            throw new ServiceException("Unable to connect to service");
        }

        return $this;
    }

    // TODO: Add support for multiple servers

    public function disconnect()
    {
        if ($this->_isValidService()) {
            $this->_service->resetServerList();
            $this->isConnected = false;
        }

        return $this;
    }

    protected function _isValidService()
    {
        $isEmpty = empty($this->_service);
        $isInstance = $this->_service instanceof \Memcached;

        if ($this->isConnected && $isInstance && !$isEmpty) {
            return true;
        }

        return false;
    }

    public function get($key, $default = null)
    {
        if (!$this->_isValidService()) {
            throw new ServiceException("Not connected to a valid service");
        }

        $value = $this->_service->get($this->prefix . $key);

        if ($value) {
            return unserialize($value);
        }

        return $default;
    }

    public function set($key, $value, $duration = "")
    {
        if (!$this->_isValidService()) {
            throw new ServiceException("Not connected to a valid service");
        }

        if (empty($duration)) {
            $duration = $this->duration;
        }
        $this->_service->set($this->prefix . $key, serialize($value), $duration);
        return $this;
    }

    public function add($key, $value, $duration = "")
    {
        if (!$this->_isValidService()) {
            throw new ServiceException("Not connected to a valid service");
        }

        if (empty($duration)) {
            $duration = $this->duration;
        }
        $this->_service->add($this->prefix . $key, serialize($value), $duration);
        return $this;
    }

    public function replace($key, $value, $duration = "")
    {
        if (!$this->_isValidService()) {
            throw new ServiceException("Not connected to a valid service");
        }

        if (empty($duration)) {
            $duration = $this->duration;
        }
        $this->_service->replace($this->prefix . $key, serialize($value), $duration);
        return $this;
    }


    public function erase($key)
    {
        return $this->delete($key);
    }

    public function delete($key)
    {
        if (!$this->_isValidService()) {
            throw new ServiceException("Not connected to a valid service");
        }

        $this->_service->delete($this->prefix . $key);
        return $this;
    }
}

<?php

namespace TeraBlaze\Cache\Driver;

class MemoryDriver extends CacheDriver
{
    /** @var array<string, mixed> */
    private array $cached = [];

    public function has($key)
    {
        return isset($this->cached[$key]) && $this->cached[$key]['expires'] > time();
    }

    public function get($key, $default = null)
    {
        if ($this->has($key)) {
            return $this->cached[$key]['value'];
        }

        return $default;
    }

    public function set($key, $value, $ttl = null)
    {
        if (!is_int($ttl)) {
            $seconds = (int) $this->config['seconds'];
        }

        $this->cached[$key] = [
            'value' => $value,
            'expires' => time() + $seconds,
        ];

        return $this;
    }

    public function delete($key): bool
    {
        unset($this->cached[$key]);
        return is_null($this->cached[$key]);
    }

    public function clear(): bool
    {
        $this->cached = [];
        return empty($this->cached);
    }
}

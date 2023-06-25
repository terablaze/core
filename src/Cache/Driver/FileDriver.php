<?php

namespace Terablaze\Cache\Driver;

use Psr\SimpleCache\InvalidArgumentException;
use ReflectionException;
use Terablaze\Cache\Lock\HasCacheLock;
use Terablaze\Cache\LockProviderInterface;

class FileDriver extends CacheDriver
{
    use HasCacheLock;

    /** @var array<string, mixed> */
    private array $cached = [];

    public function has($key, bool $fixKey = true): bool
    {
        if ($fixKey) {
            $key = $this->fixKey($key);
        }
        $data = $this->cached[$key] = $this->read($key);

        return isset($data['expires']) and $data['expires'] > time();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->fixKey($key);
        if ($this->has($key, false)) {
            return $this->cached[$key]['value'];
        }

        return $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $key = $this->fixKey($key);
        if (!is_int($ttl)) {
            $ttl = $this->ttl();
        }

        $data = $this->cached[$key] = [
            'value' => $value,
            'expires' => time() + $ttl,
        ];

        $this->write($key, $data);

        if ($this->has($key, false)) {
            return true;
        }
        return false;
    }

    public function delete($key): bool
    {
        $key = $this->fixKey($key);
        unset($this->cached[$key]);

        $path = $this->path($key);

        if (is_file($path)) {
            unlink($path);
        }

        if ($this->has($key, false)) {
            return false;
        }
        return true;
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function increment(string $key, int $incrementBy = 1): bool|int
    {
        $raw = $this->read($this->fixKey($key));

        return tap(((int)$raw['data']) + $incrementBy, function ($newValue) use ($key, $raw) {
            $this->set($key, $newValue, $raw['time'] ?? 0);
        });
    }

    public function decrement(string $key, int $decrementBy = 1): bool|int
    {
        return $this->increment($key, $decrementBy * -1);
    }

    public function clear(): bool
    {
        $this->cached = [];

        $base = $this->base();
        $separator = DIRECTORY_SEPARATOR;

        $files = glob("{$base}{$separator}*.cache");

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }

    private function write(string $key, $value): self
    {
        $data = serialize($value);
        file_put_contents($this->path($key), $data);
        return $this;
    }

    private function path(string $key): string
    {
        $base = $this->base();
        $separator = DIRECTORY_SEPARATOR;
        $key = sha1($key);

        return "{$base}{$separator}{$key}.cache";
    }

    private function base(): string
    {
        $base = $this->config['path'] ?? $this->config['root']
            ?? (kernel()->getCacheDir() . 'app_cache');
        makeDir($base);
        return $base;
    }

    private function read(string $key)
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return [];
        }

        return unserialize(file_get_contents($path));
    }
}

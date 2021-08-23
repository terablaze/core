<?php

namespace TeraBlaze\Cache\Driver;

class FileCacheDriver extends CacheDriver
{
    private array $cached = [];

    public function __construct(array $config = [])
    {
        parent::__construct($config);

//        $files = glob($this->directory . $this->prefix . 'cache.*');
//
//        if ($files) {
//            foreach ($files as $file) {
//                $time = substr(strrchr($file, '.'), 1);
//
//                if ($time < time()) {
//                    if (file_exists($file)) {
//                        unlink($file);
//                    }
//                }
//            }
//        }
    }

    public function has(string $key): bool
    {
        $data = $this->cached[$key] = $this->read($key);

        return isset($data['expires']) and $data['expires'] > time();
    }

    private function path(string $key): string
    {
        $base = $this->base();
        $separator = DIRECTORY_SEPARATOR;
        $key = sha1($key);

        return "{$base}{$separator}{$key}.json";
    }

    private function base(): string
    {
        $base = App::getInstance()->resolve('paths.base');
        $separator = DIRECTORY_SEPARATOR;

        return "{$base}{$separator}storage{$separator}framework{$separator}cache";
    }

    private function read(string $key)
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true);
    }

    public function get(string $key, $default = null)
    {
        if ($this->has($key)) {
            return $this->cached[$key]['value'];
        }

        return $default;
    }

    public function put(string $key, $value, int $seconds = null): self
    {
        if (!is_int($seconds)) {
            $seconds = (int) $this->config['seconds'];
        }

        $data = $this->cached[$key] = [
            'value' => $value,
            'expires' => time() + $seconds,
        ];

        return $this->write($key, $data);
    }

    private function write(string $key, $value)
    {
        file_put_contents($this->path($key), json_encode($value));
        return $this;
    }

    public function delete(string $key): self
    {
        unset($this->cached[$key]);

        $path = $this->path($key);

        if (is_file($path)) {
            unlink($path);
        }

        return $this;
    }

    public function clear(): self
    {
        $this->cached = [];

        $base = $this->base();
        $separator = DIRECTORY_SEPARATOR;

        $files = glob("{$base}{$separator}*.json");

        foreach ($files as $file){
            if (is_file($file)) {
                unlink($file);
            }
        }

        return $this;
    }
}

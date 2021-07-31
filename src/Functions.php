<?php

use TeraBlaze\ArrayMethods;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Exception\JsonDecodeException;
use TeraBlaze\Core\Exception\JsonEncodeException;
use TeraBlaze\HigherOrderTapProxy;

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 3/20/2017
 * Time: 11:22 AM
 */

function makeDir($dir, $recursive = true, $permissions = 0777)
{
    if (!is_dir($dir)) {
        return mkdir($dir, $permissions, $recursive);
    } else {
        return $dir;
    }
}

function jsonDecode($json, $assoc = false, $depth = 512, $options = 0)
{
    $ret = json_decode($json, $assoc, $depth, $options);
    if ($error = json_last_error()) {
        throw new JsonDecodeException(json_last_error_msg(), $error);
    }
    return $ret;
}

function jsonEncode($value, $flags = 0, $depth = 512): string
{
    $ret = json_encode($value, $flags, $depth);
    if ($error = json_last_error()) {
        throw new JsonEncodeException(json_last_error_msg(), $error);
    }
    return $ret;
}

if (!function_exists('data_set')) {
    /**
     * Set an item on an array or object using dot notation.
     *
     * @param mixed $target
     * @param string|array $key
     * @param mixed $value
     * @param bool $overwrite
     * @return mixed
     */
    function data_set(&$target, $key, $value, $overwrite = true)
    {
        $segments = is_array($key) ? $key : explode('.', $key);

        if (($segment = array_shift($segments)) === '*') {
            if (!ArrayMethods::accessible($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    data_set($inner, $segments, $value, $overwrite);
                }
            } elseif ($overwrite) {
                foreach ($target as &$inner) {
                    $inner = $value;
                }
            }
        } elseif (ArrayMethods::accessible($target)) {
            if ($segments) {
                if (!ArrayMethods::exists($target, $segment)) {
                    $target[$segment] = [];
                }

                data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || !ArrayMethods::exists($target, $segment)) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (!isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                data_set($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ($segments) {
                data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }
}

if (! function_exists('data_get')) {
    /**
     * Get an item from an array or object using "dot" notation.
     *
     * @param  mixed   $target
     * @param  string|array  $key
     * @param  mixed   $default
     * @return mixed
     */
    function data_get($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        while (! is_null($segment = array_shift($key))) {
            if ($segment === '*') {
                if ($target instanceof TeraBlaze\Collection\CollectionInterface) {
                    $target = $target->all();
                } elseif (! is_array($target)) {
                    return value($default);
                }

                $result = ArrayMethods::pluck($target, $key);

                return in_array('*', $key) ? ArrayMethods::collapse($result) : $result;
            }

            if (ArrayMethods::accessible($target) && ArrayMethods::exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }
}

if (!function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     *
     * @param mixed $value
     * @param callable|null $callback
     * @return mixed
     */
    function tap($value, $callback = null)
    {
        if (is_null($callback)) {
            return new HigherOrderTapProxy($value);
        }

        $callback($value);

        return $value;
    }
}

if (! function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    function env($key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return value($default);
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return;
        }

        if (($valueLength = strlen($value)) > 1 && $value[0] === '"' && $value[$valueLength - 1] === '"') {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

if (! function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }
}

if (! function_exists('loadConfig')) {
    /**
     * Return the default value of the given value.
     *
     * @param string $context
     * @param string|null $prefix
     * @param string[] $paths
     * @return mixed
     * @throws ReflectionException
     * @throws \TeraBlaze\Config\Exception\InvalidContextException
     */
    function loadConfig(string $context, ?string $prefix = null, array $paths = []): \TeraBlaze\Config\ConfigInterface
    {
        $container = Container::getContainer();
        /** @var \TeraBlaze\Core\Kernel\KernelInterface $kernel */
        $kernel = $container->get(\TeraBlaze\Core\Kernel\KernelInterface::class);
        if (empty($paths)) {
            $paths = [$kernel->getEnvConfigDir(), $kernel->getConfigDir()];
        }
        $config = new \TeraBlaze\Config\Config(
            $context,
            $prefix ?? $context,
            $paths
        );
        $kernel->getConfig()->merge($config);
        return $config;
    }
}

if (! function_exists('loadConfigArray')) {
    /**
     * Return the default value of the given value.
     *
     * @param string $context
     * @param string|null $prefix
     * @param string[] $paths
     * @return mixed
     * @throws ReflectionException
     * @throws \TeraBlaze\Config\Exception\InvalidContextException
     */
    function loadConfigArray(string $context, ?string $prefix = null, array $paths = [])
    {
        return (loadConfig($context, $prefix ?? $context, $paths))->toArray()[$prefix ?? $context];
    }
}

if (! function_exists('getConfig')) {
    /**
     * Retrieve a configuration option via a provided key.
     *
     * @param string $key Unique configuration option key
     * @param mixed $default Default value to return if option does not exist
     *
     * @return mixed Stored config item or $default value
     */
    function getConfig(string $key, $default = null)
    {
//        $container = Container::getContainer();
//        /** @var \TeraBlaze\Core\Kernel\KernelInterface $kernel */
//        $kernel = $container->get(\TeraBlaze\Core\Kernel\KernelInterface::class);
//
//        return $kernel->getConfig()->get($key, $default);

        return \TeraBlaze\Core\Kernel\Kernel::getConfigStatic()->get($key, $default);
    }
}

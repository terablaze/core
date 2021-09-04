<?php

use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Config\Config;
use TeraBlaze\Config\ConfigInterface;
use TeraBlaze\Config\Exception\InvalidContextException;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Exception\JsonDecodeException;
use TeraBlaze\Core\Exception\JsonEncodeException;
use TeraBlaze\Core\Kernel\KernelInterface;
use TeraBlaze\Support\HigherOrderTapProxy;
use TeraBlaze\Routing\Generator\UrlGeneratorInterface;
use TeraBlaze\Routing\Router;
use TeraBlaze\Routing\RouterInterface;

if (!function_exists('container')) {
    /**
     * @param array $services
     * @param array $parameters
     * @return Container
     */
    function container(array $services = [], array $parameters = [])
    {
        return Container::getContainer($services, $parameters);
    }
}

if (!function_exists('kernel')) {
    /**
     * Gets the active Kernel instance from the controller
     *
     * @return KernelInterface
     * @throws ReflectionException
     */
    function kernel()
    {
        /** @var KernelInterface $kernel */
        static $kernel;
        if (!$kernel) {
            $kernel = container()->get(KernelInterface::class);
        }
        return $kernel;
    }
}

/****************************
 ** ROUTER RELATED HELPERS **
 ****************************/
if (!function_exists('router')) {
    /**
     * Returns the Router object
     */
    function router()
    {
        /** @var RouterInterface $router */
        static $router;
        if (!$router) {
            $router = container()->get(Router::class);
        }

        return $router;
    }
}

if (!function_exists('route')) {
    /**
     * Generate the URL to a named route.
     *
     * @param string $path
     * @param array $parameters
     * @param int $referenceType
     * @return string
     */
    function route(string $path = '', array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return router()->getGenerator()->generate($path, $parameters, $referenceType);
    }
}

if (!function_exists('asset')) {
    function asset(string $uri = '', int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return router()->getGenerator()->generateAsset($uri, $referenceType);
    }
}
/****************************
 ** ROUTER RELATED HELPERS **
 ****************************/

if (!function_exists('makeDir')) {
    function makeDir($dir, $recursive = true, $permissions = 0777)
    {
        if (!is_dir($dir)) {
            return mkdir($dir, $permissions, $recursive);
        } else {
            return $dir;
        }
    }
}

if (!function_exists('jsonDecode')) {
    function jsonDecode($json, $assoc = false, $depth = 512, $options = 0)
    {
        $ret = json_decode($json, $assoc, $depth, $options);
        if ($error = json_last_error()) {
            throw new JsonDecodeException(json_last_error_msg(), $error);
        }
        return $ret;
    }
}

if (!function_exists('jsonEncode')) {
    function jsonEncode($value, $flags = 0, $depth = 512): string
    {
        $ret = json_encode($value, $flags, $depth);
        if ($error = json_last_error()) {
            throw new JsonEncodeException(json_last_error_msg(), $error);
        }
        return $ret;
    }
}

if (!function_exists('dataSet')) {
    /**
     * Set an item on an array or object using dot notation.
     *
     * @param mixed $target
     * @param string|array $key
     * @param mixed $value
     * @param bool $overwrite
     * @return mixed
     */
    function dataSet(&$target, $key, $value, $overwrite = true)
    {
        $segments = is_array($key) ? $key : explode('.', $key);

        if (($segment = array_shift($segments)) === '*') {
            if (!ArrayMethods::accessible($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    dataSet($inner, $segments, $value, $overwrite);
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

                dataSet($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || !ArrayMethods::exists($target, $segment)) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (!isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                dataSet($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ($segments) {
                dataSet($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }
}

if (!function_exists('dataGet')) {
    /**
     * Get an item from an array or object using "dot" notation.
     *
     * @param mixed $target
     * @param string|array $key
     * @param mixed $default
     * @return mixed
     */
    function dataGet($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        while (!is_null($segment = array_shift($key))) {
            if ($segment === '*') {
                if ($target instanceof TeraBlaze\Collection\CollectionInterface) {
                    $target = $target->all();
                } elseif (!is_array($target)) {
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

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param string $key
     * @param mixed $default
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
                return null;
        }

        if (($valueLength = strlen($value)) > 1 && $value[0] === '"' && $value[$valueLength - 1] === '"') {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     * @return mixed
     */
    function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }
}

if (!function_exists('loadConfig')) {
    /**
     * Return the default value of the given value.
     *
     * @param string $context
     * @param string|null $prefix
     * @param string[] $paths
     * @return mixed
     * @throws ReflectionException
     * @throws InvalidContextException
     */
    function loadConfig(string $context, ?string $prefix = null, array $paths = []): ConfigInterface
    {
        $kernel = kernel();
        if (empty($paths)) {
            $paths = [$kernel->getEnvConfigDir(), $kernel->getConfigDir()];
        }
        $config = new Config(
            $context,
            $prefix ?? $context,
            $paths
        );
        $kernel->getConfig()->merge($config);
        return $config;
    }
}

if (!function_exists('loadConfigArray')) {
    /**
     * Return the default value of the given value.
     *
     * @param string $context
     * @param string|null $prefix
     * @param string[] $paths
     * @return mixed
     * @throws ReflectionException
     * @throws InvalidContextException
     */
    function loadConfigArray(string $context, ?string $prefix = null, array $paths = [])
    {
        return (loadConfig($context, $prefix ?? $context, $paths))->toArray()[$prefix ?? $context];
    }
}

if (!function_exists('getConfig')) {
    /**
     * Retrieve a configuration option via a provided key.
     *
     * @param string $key Unique configuration option key
     * @param mixed $default Default value to return if option does not exist
     *
     * @return mixed Stored config item or $default value
     * @throws ReflectionException
     */
    function getConfig(string $key, $default = null)
    {
        /** @var ConfigInterface $config */
        static $config;
        if (!$config) {
            $config = kernel()->getConfig();
        }
        return $config->get($key, $default);
    }
}

if (!function_exists('baseDir')) {
    function baseDir(string $path = '', bool $trailingSlash = false)
    {
        $baseDir = kernel()->getProjectDir();

        return $baseDir . normalizeDir($path, $trailingSlash);
    }
}

if (!function_exists('publicDir')) {
    function publicDir(string $path = '', bool $trailingSlash = false)
    {
        return baseDir('public' . normalizeDir($path), $trailingSlash);
    }
}

if (!function_exists('configDir')) {
    function configDir(string $path = '', bool $trailingSlash = false)
    {
        return baseDir('config' . normalizeDir($path), $trailingSlash);
    }
}

if (!function_exists('storageDir')) {
    function storageDir(string $path = '', bool $trailingSlash = false)
    {
        return baseDir('storage' . normalizeDir($path), $trailingSlash);
    }
}

if (!function_exists('normalizeDir')) {
    function normalizeDir(string $path, bool $trailingSlash = false)
    {
        $path = trim($path, DIRECTORY_SEPARATOR);
        $path = "/{$path}/";
        $replacePattern = "/[\/\\\\\\" . DIRECTORY_SEPARATOR . "]{2,}/";
        $path = preg_replace($replacePattern, DIRECTORY_SEPARATOR, $path);
        return $trailingSlash ? $path : rtrim($path, DIRECTORY_SEPARATOR);
    }
}
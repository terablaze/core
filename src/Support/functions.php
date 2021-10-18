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
use TeraBlaze\Support\StringMethods;
use TeraBlaze\Validation\ValidatorInterface;

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
     * Gets the active Kernel instance from the contianer
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

if (!function_exists('request')) {
    /**
     * Gets the current request instance instance from the kernel
     *
     * @return \TeraBlaze\HttpBase\Request
     * @throws ReflectionException
     */
    function request()
    {
        return kernel()->getCurrentRequest();
    }
}

if (!function_exists('session')) {
    function session($key = null, $default = null)
    {
        if (is_null($key)) {
            return request()->getSession();
        }
        if (is_array($key)) {
            foreach ($key as $sessionKey => $sessionValue) {
                request()->getSession()->set($sessionKey, $sessionValue);
            }
            return null;
        }
        return request()->getSession()->get($key, $default);
    }
}

if (!function_exists('flash')) {
    function flash($key = null, $default = null)
    {
        if (is_null($key)) {
            return session()->getFlash();
        }
        if (is_array($key)) {
            foreach ($key as $sessionKey => $sessionValue) {
                session()->getFlash()->flash($sessionKey, $sessionValue);
            }
            return null;
        }
        return session()->getFlash()->getFlash($key, $default);
    }
}

if (!function_exists('addFlash')) {
    function addFlash($key, $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $sessionKey => $sessionValue) {
                addFlash($sessionKey, $sessionValue);
            }
            return;
        }
        flash()->flash($key, $value);
    }
}
if (!function_exists('addFlashNow')) {
    function addFlashNow($key, $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $sessionKey => $sessionValue) {
                addFlashNow($sessionKey, $sessionValue);
            }
            return;
        }
        flash()->flashNow($key, $value);
    }
}

if (!function_exists('csrf')) {
    function csrf()
    {
        return session()->getCsrf();
    }
}

if (!function_exists('getLocales')) {
    /**
     * @return string[]
     */
    function getLocales(): array
    {
        static $locales;
        if (!$locales) {
            $locales = array_unique(array_merge(
                getConfig('app.locales'),
                ArrayMethods::wrap(getConfig('app.locale'))
            ), SORT_REGULAR);
        }
        return $locales;
    }
}

if (!function_exists('getLocaleFromHost')) {
    /**
     * Attempts to get the locale of a url by checking if the first part
     * of the host is among supported locales
     *
     * @param string $host // valid uri host
     * @return string
     */
    function getLocaleFromHost(string $host): string
    {
        $resolvedLocale = "";
        foreach (getLocales() as $locale) {
            if (StringMethods::startsWith($host, "$locale.")) {
                $resolvedLocale = $locale;
                break;
            }
        }
        return $resolvedLocale;
    }
}

if (!function_exists('getLocaleFromPath')) {
    /**
     * Attempts to get the locale of a url by checking if the first part
     * of the path is among supported locales
     *
     * @param string $path // valid uri path
     * @return string
     */
    function getLocaleFromPath(string $path): string
    {
        $resolvedLocale = "";
        $path = trim($path, '/');
        $possibleLocale = (explode('/', $path)[0]);
        foreach (getLocales() as $locale) {
            if ($locale === $possibleLocale) {
                $resolvedLocale = $locale;
                break;
            }
        }
        return $resolvedLocale;
    }
}

if (!function_exists('getLocaleFromSession')) {
    /**
     * Attempts to get the locale stored in session's app_locale key
     *
     * @return string
     */
    function getLocaleFromSession(): string
    {
        $resolvedLocale = "";
        if (in_array($locale = session('app_locale'), getLocales(), true)) {
            $resolvedLocale = $locale;
        }
        return $resolvedLocale;
    }
}

if (!function_exists('getCurrentLocale')) {
    /**
     * Returns the current locale the app is using which is either
     * explicitly set in hist, path or session, or the default locale
     * set in app.locale config value
     *
     * @return mixed|string
     * @throws ReflectionException
     */
    function getCurrentLocale()
    {
        static $currentLocale;
        if (!$currentLocale) {
            $currentLocale = getExplicitlySetLocale() ?: getConfig('app.locale');
        }
        return $currentLocale;
    }
}

if (!function_exists('getExplicitlySetLocale')) {
    /**
     * Returns the explicitly set locale in either the
     * host, path or session
     *
     * @return mixed|string
     * @throws ReflectionException
     */
    function getExplicitlySetLocale()
    {
        if (kernel()->inConsole()) {
            return "";
        }
        static $explicitlySetLocale;
        if (is_null($explicitlySetLocale)) {
            $localeType = getConfig('app.locale_type', 'path');
            switch ($localeType) {
                case 'session':
                    $explicitlySetLocale = getLocaleFromSession();
                    break;
                case 'path':
                    $explicitlySetLocale = getLocaleFromPath(request()->path());
                    break;
                case 'host':
                    $explicitlySetLocale = getLocaleFromHost(request()->getUriString());
                    break;
                default:
                    $explicitlySetLocale = "";
            }
        }
        return $explicitlySetLocale;
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
     * @param string|null $locale
     * @return string
     */
    function route(
        string $path = '',
        array $parameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
        ?string $locale = null
    ) {
        return router()->getGenerator()->generate($path, $parameters, $referenceType, $locale);
    }
}

if (!function_exists('asset')) {
    function asset(string $uri = '', int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        if ($assetUrl = getConfig('app.asset_url')) {
            $uri = "$assetUrl/$uri";
        }
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

if (!function_exists('setConfig')) {
    /**
     * Retrieve a configuration option via a provided key.
     *
     * @param string $key Unique configuration option key
     * @param mixed $value Default value to return if option does not exist
     *
     * @return mixed Stored config item or $default value
     * @throws ReflectionException
     */
    function setConfig(string $key, $value = null)
    {
        /** @var ConfigInterface $config */
        static $config;
        if (!$config) {
            $config = kernel()->getConfig();
        }
        return $config->set($key, $value);
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

if (!function_exists('timeElapsedString')) {

    function timeElapsedString($datetime, $full = false)
    {
        $now = new \DateTime();
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) {
            $string = array_slice($string, 0, 1);
        }
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}

if (!function_exists('with')) {
    /**
     * Return the given value, optionally passed through the given callback.
     *
     * @param mixed $value
     * @param callable|null $callback
     * @return mixed
     */
    function with($value, callable $callback = null)
    {
        return is_null($callback) ? $value : $callback($value);
    }
}

if (!function_exists('isWindowsOs')) {
    /**
     * Determine whether the current environment is Windows based.
     *
     * @return bool
     */
    function isWindowsOs()
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}

if (!function_exists('validator')) {
    function validator(): ValidatorInterface
    {
        /** @var ValidatorInterface $validator */
        static $validator;
        if (!$validator) {
            $validator = container()->get(ValidatorInterface::class);
        }
        return $validator;
    }
}

if (!function_exists('validate')) {
    function validate(array $data, array $rules, array $messages = []): array
    {
        return validator()->validate($data, $rules, $messages);
    }
}
<?php

namespace Terablaze\Support;

use BackedEnum;
use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionException;
use Terablaze\Bus\Dispatcher;
use Terablaze\Bus\Pending\PendingClosureDispatch;
use Terablaze\Bus\Pending\PendingDispatch;
use Terablaze\Config\Config;
use Terablaze\Config\ConfigInterface;
use Terablaze\Config\Exception\InvalidContextException;
use Terablaze\Container\Container;
use Terablaze\Container\Exception\ContainerException;
use Terablaze\Container\Exception\ParameterNotFoundException;
use Terablaze\Core\Exception\JsonDecodeException;
use Terablaze\Core\Exception\JsonEncodeException;
use Terablaze\Core\Kernel\KernelInterface;
use Terablaze\ErrorHandler\Exception\Http\HttpException;
use Terablaze\ErrorHandler\Exception\Http\NotFoundHttpException;
use Terablaze\HttpBase\Response;
use Terablaze\Log\LogManager;
use Terablaze\Queue\CallQueuedClosure;
use Terablaze\Routing\Generator\UrlGeneratorInterface;
use Terablaze\Routing\Router;
use Terablaze\Routing\RouterInterface;
use Terablaze\Support\ArrayMethods;
use Terablaze\Support\HigherOrderTapProxy;
use Terablaze\Support\Interfaces\DeferringDisplayableValue;
use Terablaze\Support\Interfaces\Htmlable;
use Terablaze\Support\StringMethods;
use Terablaze\Validation\Validator;

class Helpers
{
    /**
     * @param array $services
     * @param array $parameters
     * @return Container
     */
    public static function container(array $services = [], array $parameters = [])
    {
        return Container::getContainer($services, $parameters);
    }

    /**
     * @param string $serviceId
     * @return mixed|object
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public static function service(string $serviceId)
    {
        return container()->get($serviceId);
    }

    /**
     * @param string $parameterName
     * @param mixed $default
     * @return mixed
     * @throws ContainerException
     * @throws ParameterNotFoundException
     */
    public static function parameter(string $parameterName, $default = null)
    {
        if (container()->hasParameter($parameterName)) {
            return container()->getParameter($parameterName);
        }
        return $default;
    }

    /**
     * Gets the active Kernel instance from the contianer
     *
     * @return KernelInterface
     * @throws ReflectionException
     */
    public static function kernel()
    {
        /** @var KernelInterface $kernel */
        static $kernel;
        if (!$kernel) {
            $kernel = container()->get(KernelInterface::class);
        }
        return $kernel;
    }

    /**
     * Throw an HttpException with the given data.
     *
     * @param Response|int $code
     * @param string $message
     * @param array $headers
     * @return void
     *
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws ReflectionException
     */
    public static function abort($code, string $message = '', array $headers = [])
    {
        if ($code instanceof Response) {
            throw new \Terablaze\ErrorHandler\Exception\Http\HttpResponseException($code);
        }

        kernel()->abort($code, $message, $headers);
    }

    /**
     * Throw an HttpException with the given data if the given condition is true.
     *
     * @param bool $boolean
     * @param Response|int $code
     * @param string $message
     * @param array $headers
     * @return void
     *
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws ReflectionException
     */
    public static function abortIf($boolean, $code, string $message = '', array $headers = [])
    {
        if ($boolean) {
            abort($code, $message, $headers);
        }
    }

    /**
     * Throw an HttpException with the given data unless the given condition is true.
     *
     * @param bool $boolean
     * @param Response|int $code
     * @param string $message
     * @param array $headers
     * @return void
     *
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws ReflectionException
     */
    public static function abortUnless($boolean, $code, string $message = '', array $headers = [])
    {
        if (!$boolean) {
            abort($code, $message, $headers);
        }
    }

    /**
     * Gets the current request instance instance from the kernel
     *
     * @return \Terablaze\HttpBase\Request
     * @throws ReflectionException
     */
    public static function request()
    {
        return static::kernel()->getCurrentRequest();
    }

    /**
     * Report an exception.
     *
     * @param  \Throwable|string  $exception
     * @return void
     */
    public static function report($exception)
    {
        if (is_string($exception)) {
            $exception = new \Exception($exception);
        }

        (static::kernel()->getExceptionHandler())->report($exception);
    }

    /**
     * Report an exception if the given condition is true.
     *
     * @param  bool  $boolean
     * @param  \Throwable|string  $exception
     * @return void
     */
    function report_if($boolean, $exception)
    {
        if ($boolean) {
            static::report($exception);
        }
    }

    /**
     * Report an exception unless the given condition is true.
     *
     * @param  bool  $boolean
     * @param  \Throwable|string  $exception
     * @return void
     */
    function report_unless($boolean, $exception)
    {
        if (! $boolean) {
            static::report($exception);
        }
    }

    public static function session($key = null, $default = null)
    {
        if (is_null($key)) {
            return static::request()->getSession();
        }
        if (is_array($key)) {
            foreach ($key as $sessionKey => $sessionValue) {
                static::request()->getSession()->set($sessionKey, $sessionValue);
            }
            return null;
        }
        return static::request()->getSession()->get($key, $default);
    }

    public static function flash($key = null, $default = null)
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

    public static function addFlash($key, $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $sessionKey => $sessionValue) {
                addFlash($sessionKey, $sessionValue);
            }
            return;
        }
        flash()->flash($key, $value);
    }

    public static function addFlashNow($key, $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $sessionKey => $sessionValue) {
                addFlashNow($sessionKey, $sessionValue);
            }
            return;
        }
        flash()->flashNow($key, $value);
    }

    public static function csrf()
    {
        return session()->getCsrf();
    }

    /**
     * Retrieve an old input item.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public static function old($key = null, $default = null)
    {
        return request()->old($key, $default);
    }

    /**
     * Retrieve a flashed validation error
     *
     * @param string|null $key
     * @param bool $all
     * @return mixed
     */
    public static function error($key = null, bool $all = false)
    {
        return request()->error($key, $all);
    }

    /**
     * @return string[]
     */
    public static function getLocales(): array
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

    /**
     * Attempts to get the locale of a url by checking if the first part
     * of the host is among supported locales
     *
     * @param string $host // valid uri host
     * @return string
     */
    public static function getLocaleFromHost(string $host): string
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

    /**
     * Attempts to get the locale of a url by checking if the first part
     * of the path is among supported locales
     *
     * @param string $path // valid uri path
     * @return string
     */
    public static function getLocaleFromPath(string $path): string
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

    /**
     * Attempts to get the locale stored in session's app_locale key
     *
     * @return string
     */
    public static function getLocaleFromSession(): string
    {
        $resolvedLocale = "";
        if (in_array($locale = session('app_locale'), getLocales(), true)) {
            $resolvedLocale = $locale;
        }
        return $resolvedLocale;
    }

    /**
     * Returns the current locale the app is using which is either
     * explicitly set in hist, path or session, or the default locale
     * set in app.locale config value
     *
     * @return mixed|string
     * @throws ReflectionException
     */
    public static function getCurrentLocale()
    {
        static $currentLocale;
        if (!$currentLocale) {
            $currentLocale = getExplicitlySetLocale() ?: getConfig('app.locale');
        }
        return $currentLocale;
    }

    /**
     * Returns the explicitly set locale in either the
     * host, path or session
     *
     * @return mixed|string
     * @throws ReflectionException
     */
    public static function getExplicitlySetLocale()
    {
        if (static::kernel()->inConsole()) {
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

    /****************************
     ** ROUTER RELATED HELPERS **
     ****************************/
    /**
     * Returns the Router object
     */
    public static function router()
    {
        /** @var RouterInterface $router */
        static $router;
        if (!$router) {
            $router = container()->get(Router::class);
        }

        return $router;
    }

    /**
     * Generate the URL to a named route.
     *
     * @param string $path
     * @param array $parameters
     * @param int $referenceType
     * @param string|null $locale
     * @return string
     */
    public static function route(
        string  $path = '',
        array   $parameters = [],
        int     $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
        ?string $locale = null
    )
    {
        return router()->getGenerator()->generate($path, $parameters, $referenceType, $locale);
    }

    /**
     * Generate the absolute URL to a named route.
     *
     * @param string $path
     * @param array $parameters
     * @param string|null $locale
     * @return string
     */
    public static function absoluteRoute(string $path = '', array $parameters = [], ?string $locale = null)
    {
        return route($path, $parameters, UrlGeneratorInterface::ABSOLUTE_URL, $locale);
    }

    public static function asset(string $uri = '', int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        if ($assetUrl = getConfig('app.asset_url')) {
            $uri = "$assetUrl/$uri";
        }
        return router()->getGenerator()->generateAsset($uri, $referenceType);
    }
    /****************************
     ** ROUTER RELATED HELPERS **
     ****************************/

    public static function makeDir($dir, $recursive = true, $permissions = 0777)
    {
        if (!is_dir($dir)) {
            return mkdir($dir, $permissions, $recursive);
        } else {
            return $dir;
        }
    }

    public static function jsonDecode($json, $assoc = false, $depth = 512, $options = 0)
    {
        $ret = json_decode($json, $assoc, $depth, $options);
        if ($error = json_last_error()) {
            throw new JsonDecodeException(json_last_error_msg(), $error);
        }
        return $ret;
    }

    public static function jsonEncode($value, $flags = 0, $depth = 512): string
    {
        $ret = json_encode($value, $flags, $depth);
        if ($error = json_last_error()) {
            throw new JsonEncodeException(json_last_error_msg(), $error);
        }
        return $ret;
    }

    /**
     * Create a collection from the given value.
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param \Terablaze\Support\Interfaces\Arrayable<TKey, TValue>|iterable<TKey, TValue>|null $value
     * @return \Terablaze\Collection\ArrayCollection<TKey, TValue>
     */
    public static function collect($value = null)
    {
        return new \Terablaze\Collection\ArrayCollection($value);
    }

    /**
     * Set an item on an array or object using dot notation.
     *
     * @param mixed $target
     * @param string|array $key
     * @param mixed $value
     * @param bool $overwrite
     * @return mixed
     */
    public static function dataSet(&$target, $key, $value, $overwrite = true)
    {
        $segments = is_array($key) ? $key : explode('.', $key);

        if (($segment = array_shift($segments)) === '*') {
            if (!ArrayMethods::accessible($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    static::dataSet($inner, $segments, $value, $overwrite);
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

                static::dataSet($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || !ArrayMethods::exists($target, $segment)) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (!isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                static::dataSet($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ($segments) {
                static::dataSet($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }

    /**
     * Get an item from an array or object using "dot" notation.
     *
     * @param mixed $target
     * @param string|array $key
     * @param mixed $default
     * @return mixed
     */
    public static function dataGet($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        while (!is_null($segment = array_shift($key))) {
            if ($segment === '*') {
                if ($target instanceof \Terablaze\Collection\CollectionInterface) {
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

    /**
     * Get the first element of an array. Useful for method chaining.
     *
     * @param array $array
     * @return mixed
     */
    public static function head($array)
    {
        return reset($array);
    }

    /**
     * Get the last element from an array.
     *
     * @param array $array
     * @return mixed
     */
    public static function last($array)
    {
        return end($array);
    }

    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function value($value, ...$args)
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }

    /**
     * Get a new stringable object from the given string.
     *
     * @param string|null $string
     * @return Stringable|mixed
     */
    public static function str($string = null)
    {
        if (func_num_args() === 0) {
            return new class {
                public function __call($method, $parameters)
                {
                    return StringMethods::$method(...$parameters);
                }

                public function __toString()
                {
                    return '';
                }
            };
        }

        return StringMethods::of($string);
    }

    /**
     * Call the given Closure with the given value then return the value.
     *
     * @param mixed $value
     * @param callable|null $callback
     * @return mixed
     */
    public static function tap($value, $callback = null)
    {
        if (is_null($callback)) {
            return new HigherOrderTapProxy($value);
        }

        $callback($value);

        return $value;
    }

    /**
     * Encode HTML special characters in a string.
     *
     * @param DeferringDisplayableValue|Htmlable|BackedEnum|string|null $value
     * @param bool $doubleEncode
     * @return string
     */
    public static function e($value, $doubleEncode = true)
    {
        if ($value instanceof \Terablaze\Support\Interfaces\DeferringDisplayableValue) {
            $value = $value->resolveDisplayableValue();
        }

        if ($value instanceof Htmlable) {
            return $value->toHtml();
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8', $doubleEncode);
    }

    /**
     * Gets the value of an environment variable.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function env($key, $default = null)
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
    public static function loadConfig(string $context, ?string $prefix = null, array $paths = []): ConfigInterface
    {
        $kernel = static::kernel();
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
    public static function loadConfigArray(string $context, ?string $prefix = null, array $paths = [])
    {
        return (loadConfig($context, $prefix ?? $context, $paths))->toArray()[$prefix ?? $context];
    }

    /**
     * Retrieve a configuration option via a provided key.
     *
     * @param string $key Unique configuration option key
     * @param mixed $default Default value to return if option does not exist
     *
     * @return mixed Stored config item or $default value
     * @throws ReflectionException
     */
    public static function getConfig(string $key, $default = null)
    {
        /** @var ConfigInterface $config */
        static $config;
        if (!$config) {
            $config = static::kernel()->getConfig();
        }
        return $config->get($key, $default);
    }

    /**
     * Set a configuration option via a provided key.
     *
     * @param string $key Unique configuration option key
     * @param mixed $value Default value to return if option does not exist
     *
     * @return mixed Stored config item or $default value
     * @throws ReflectionException
     */
    public static function setConfig(string $key, $value = null)
    {
        /** @var ConfigInterface $config */
        static $config;
        if (!$config) {
            $config = static::kernel()->getConfig();
        }
        return $config->set($key, $value);
    }

    /**
     * @return LoggerInterface|LogManager
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public static function logger(): LoggerInterface
    {
        /** @var LoggerInterface $logger */
        static $logger;
        if (!$logger || $logger instanceof NullLogger) {
            try {
                $logger = container()->get(LoggerInterface::class);
            } catch (\Exception $exception) {
                $logger = new NullLogger();
            }
        }
        return $logger;
    }

    public static function baseDir(string $path = '', bool $trailingSlash = false)
    {
        $baseDir = static::kernel()->getProjectDir();

        return $baseDir . normalizeDir($path, $trailingSlash);
    }

    public static function publicDir(string $path = '', bool $trailingSlash = false)
    {
        return baseDir('public' . normalizeDir($path), $trailingSlash);
    }

    public static function configDir(string $path = '', bool $trailingSlash = false)
    {
        return baseDir('config' . normalizeDir($path), $trailingSlash);
    }

    public static function storageDir(string $path = '', bool $trailingSlash = false)
    {
        return baseDir('storage' . normalizeDir($path), $trailingSlash);
    }

    public static function databaseDir(string $path = '', bool $trailingSlash = false)
    {
        return baseDir('database' . normalizeDir($path), $trailingSlash);
    }

    public static function normalizeDir(string $path, bool $trailingSlash = false)
    {
        $path = trim($path, DIRECTORY_SEPARATOR);
        $path = "/{$path}/";
        $replacePattern = "/[\/\\\\\\" . DIRECTORY_SEPARATOR . "]{2,}/";
        $path = preg_replace($replacePattern, DIRECTORY_SEPARATOR, $path);
        return $trailingSlash ? $path : rtrim($path, DIRECTORY_SEPARATOR);
    }


    public static function timeElapsedString($datetime, $full = false)
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

    /**
     * Return the given value, optionally passed through the given callback.
     *
     * @param mixed $value
     * @param callable|null $callback
     * @return mixed
     */
    public static function with($value, callable $callback = null)
    {
        return is_null($callback) ? $value : $callback($value);
    }

    /**
     * Determine whether the current environment is Windows based.
     *
     * @return bool
     */
    public static function isWindowsOs()
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    public static function validator(): Validator
    {
        /** @var Validator $validator */
        static $validator;
        if (!$validator) {
            $validator = container()->make(Validator::class);
        }
        return $validator;
    }

    public static function validate(array $data, array $rules, array $messages = [], array $customFields = []): array
    {
        return validator()->make($data, $rules, $messages, $customFields)->validate();
    }

    public static function encrypter(): \Terablaze\Encryption\Encrypter
    {
        if (!container()->has("encrypter")) {
            throw new \RuntimeException("Encryption service not found, ensure it has been loaded in parcels");
        }
        return container()->get("encrypter");
    }

    public static function encrypt($value, bool $serialize = true): string
    {
        return encrypter()->encrypt($value, $serialize);
    }

    public static function encryptString(string $value)
    {
        return encrypter()->encryptString($value);
    }

    public static function decrypt(string $payload, bool $unserialize = true): string
    {
        return encrypter()->decrypt($payload, $unserialize);
    }

    public static function decryptString(string $payload)
    {
        return encrypter()->decryptString($payload);
    }

    /**
     * Dispatch a job to its appropriate handler.
     *
     * @param  mixed  $job
     * @return PendingDispatch
     */
    public static function dispatch($job)
    {
        return $job instanceof Closure
            ? new PendingClosureDispatch(CallQueuedClosure::create($job))
            : new PendingDispatch($job);
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * Queueable jobs will be dispatched to the "sync" queue.
     *
     * @param  mixed  $job
     * @param  mixed  $handler
     * @return mixed
     */
    function dispatchSync($job, $handler = null)
    {
        return static::container()->get(Dispatcher::class)->dispatchSync($job, $handler);
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * @param  mixed  $job
     * @param  mixed  $handler
     * @return mixed
     *
     * @deprecated Will be removed in a future Laravel version.
     */
    function dispatchNow($job, $handler = null)
    {
        return static::container()->get(Dispatcher::class)->dispatchNow($job, $handler);
    }

    /**
     * Get the class "basename" of the given object / class.
     *
     * @param string|object $class
     * @return string
     */
    public static function classBasename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }

    /**
     * Returns all traits used by a class, its parent classes and trait of their traits.
     *
     * @param  object|string  $class
     * @return array
     */
    public static function classUsesRecursive($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        foreach (array_reverse(class_parents($class)) + [$class => $class] as $class) {
            $results += static::traitUsesRecursive($class);
        }

        return array_unique($results);
    }

    /**
     * Returns all traits used by a trait and its traits.
     *
     * @param  string  $trait
     * @return array
     */
    public static function traitUsesRecursive($trait)
    {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $trait) {
            $traits += static::traitUsesRecursive($trait);
        }

        return $traits;
    }
}

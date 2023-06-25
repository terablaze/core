<?php

namespace Terablaze\Routing;

use Exception;
use Terablaze\Container\Container;
use Terablaze\HttpBase\RedirectResponse;
use Terablaze\Support\ArrayMethods;
use Terablaze\Support\Helpers;

/**
 * Class Route
 *
 * @package Terablaze\Routing
 */
class Route
{
    private Container $container;

    private Router $router;

    /** @var string $name */
    public string $name;

    /** @var string $pattern */
    public $pattern;

    /** @var string $action */
    public $action;

    /** @var string[] $method */
    public $method;

    /** @var string $controller */
    public $controller;

    /** @var bool $expectsJson */
    public $expectsJson;

    /** @var array<string|int, mixed> $parameters */
    public array $parameters = [];

    /** @var string[] $middlewares */
    public array $middlewares = [];

    public $callableRoute;

    /**
     * @var string
     */
    private string $explicitlySetLocale;
    /**
     * @var string
     */
    private string $currentLocale;
    /**
     * @var string
     */
    private string $localeType;

    /**
     * All of the verbs supported by the router.
     *
     * @var string[]
     */
    public static $verbs = ['get', 'head', 'post', 'put', 'patch', 'delete', 'options'];

    /**
     * Route constructor.
     * @param int|string $name
     * @param callable|array<string, mixed> $route
     */
    public function __construct(Container $container, string $name = "", $route = [])
    {
        $this->container = $container;

        /** @var RouterInterface $router */
        $this->router = $this->container->get(RouterInterface::class);

        $this->name = (string)$name;

        $this->pattern = $route['pattern'] ?? '/';
        unset($route['pattern']);

        if (isset($route['action']) && is_array($route['action'])) {
            [$this->controller, $this->action] = $route['action'] ?? null;
            unset($route['action']);
        } else {
            $this->controller = $route['controller'] ?? null;
            unset($route['controller']);
            $this->action = $route['action'] ?? null;
            unset($route['action']);
        }

        $this->method = ArrayMethods::wrap($route['method'] ?? $route['methods'] ?? '');
        unset($route['method']);
        unset($route['methods']);

        $this->expectsJson = $route['expects_json'] ?? false;
        unset($route['expects_json']);

        $this->middlewares = ArrayMethods::wrap($route['middleware'] ?? $route['middlewares'] ?? []);
        unset($route['middleware']);
        unset($route['middlewares']);

        foreach ($route as $callable) {
            if (is_callable($callable)) {
                $this->callableRoute = $callable;
            }
        }

        $this->explicitlySetLocale = Helpers::getExplicitlySetLocale();
        $this->currentLocale = Helpers::getCurrentLocale();
        $this->localeType = Helpers::getConfig('app.locale_type', 'path');
    }

    /**
     * @param string $name
     * @return static
     */
    public function setName(string $name): static
    {
        if ($this->router->hasRoute($name)) {
            throw new Exception(sprintf("Route name %s already exists", $name));
        }

        $this->name = $name;
        $this->router->syncRouteName($this);
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    public function name(?string $name = null): string|static
    {
        return is_null($name) ? $this->getName() : $this->setName($name);
    }

    /**
     * @return string
     */
    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    /**
     * @return string
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @return string[]
     */
    public function getMethod(): array
    {
        return $this->method;
    }

    /**
     * @return string[]
     */
    public function getCleanedMethod(): array
    {
        $method = array_map(function (string $methodItem) {
            return strtolower($methodItem);
        }, $this->method);

        return ArrayMethods::clean($method);
    }

    /**
     * @return string
     */
    public function getController(): ?string
    {
        return $this->controller;
    }

    /**
     * @return array<string|int, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param bool $expectsJson
     *
     * @return static
     */
    public function setExpectsJson(bool $expectsJson = true): static
    {
        $this->expectsJson = $expectsJson;
        return $this;
    }

    /**
     * @return bool
     */
    public function isExpectsJson(): bool
    {
        return $this->expectsJson;
    }

    public function expectsJson(?bool $expectsJson = null): bool|static
    {
        return is_null($expectsJson) ? $this->isExpectsJson() : $this->setExpectsJson($expectsJson);
    }

    /**
     * @return static
     */
    public function setMiddlewares($middlewares): static
    {
        $this->middlewares += ArrayMethods::wrap($middlewares);
        return $this;
    }

    /**
     * @return static
     */
    public function setMiddleware($middleware): static
    {
        return $this->setMiddlewares($middleware);
    }

    /**
     * @return array|mixed|string[]
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * @return array|mixed|string[]
     */
    public function getMiddleware(): array
    {
        return $this->getMiddlewares();
    }

    public function middlewares(array|string|null $middlewares = null): array|static
    {
        return is_null($middlewares) ? $this->getMiddlewares() : $this->setMiddlewares($middlewares);
    }

    public function middleware(array|string|null $middlewares = null): array|static
    {
        return $this->middlewares($middlewares);
    }

    public function isCallableRoute(): bool
    {
        return is_callable($this->callableRoute);
    }

    public function getCallable()
    {
        return $this->callableRoute;
    }

    /**
     * @param string $path
     * @return bool
     */
    public function matches(string $path): bool
    {
        $path = $this->normalizePath($path);
        if ($this->isDirectMatch($path)) {
            $this->setLocale($this->currentLocale);
            return true;
        }

        if ($this->isMatch($path)) {
            $this->setLocale($this->currentLocale);
            return true;
        }
        return false;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path, '/');
        $path = "/{$path}/";
        return preg_replace('/[\/]{2,}/', '/', $path);
    }

    private function isDirectMatch(string $path): bool
    {
        return $path === $this->getLocaledPattern();
    }

    private function isMatch(string $path): bool
    {
        $pattern = $this->getLocaledPattern();

        preg_match_all("#" . Router::NAMED_ROUTE_MATCH . "|:([a-zA-Z0-9]+)#", $pattern, $keys);

        $keysToReplace = [];
        $keyPatterns = [];
        foreach ($keys[0] as $key) {
            $keysToReplace[] = "{$key}";
            $keyParts = explode(":", trim($key, "{}"), 3);
            $keyPattern = $keyParts[1] ?? "any";
            $keyDefault = $keyParts[2] ?? null;
            $keyPatterns[] = in_array("#(:" . $keyPattern . ")#", Router::PATTERN_KEYS) ?
                ":" . $keyPattern : $keyPattern;
        }

        $pattern = str_replace($keysToReplace, $keyPatterns, $pattern);

        unset($keys);

        // get keys
        preg_match_all("#:([a-zA-Z0-9]+)#", $pattern, $keys);

        if (sizeof($keys) && sizeof($keys[0]) && sizeof($keys[1])) {
            $keys = $keys[1];
        } else {
            // no keys in the pattern, return a simple match
            $this->setLocale($this->currentLocale);
            return preg_match("#^{$pattern}$#", $path);
        }

        // normalize route pattern
        $pattern = preg_replace(Router::PATTERN_KEYS, Router::PATTERN_KEYS_REPLACEMENTS, $pattern);

        // check values
        preg_match_all("#^{$pattern}$#", $path, $values);

        if (sizeof($values) && sizeof($values[0]) && sizeof($values[1])) {
            // unset the matched url
            unset($values[0]);

            // values found, modify parameters and return
            //$derived = array_combine($keys, ArrayMethods::flatten($values));
            $derived = ArrayMethods::flatten($values);
            $this->parameters = array_merge($this->parameters, $derived);

            $this->setLocale($this->currentLocale);
            return true;
        }
        return false;
    }

    private function getLocaledPattern(): string
    {
        $pathLocalePrefix = "";
        if ($this->localeType === 'path' && $this->explicitlySetLocale !== "") {
            $pathLocalePrefix = "/$this->currentLocale/";
        }
        return $this->normalizePath($pathLocalePrefix . $this->pattern);
    }

    private function setLocale($locale = ''): void
    {
        if (!empty($locale)) {
            $this->container->get('translator')->setLocale($locale);
        }
    }

    /**
     * Register a new GET route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Terablaze\Routing\Route
     */
    public static function get($uri, $action = null)
    {
        return static::addRoute(['get', 'head'], $uri, $action);
    }

    /**
     * Register a new POST route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Terablaze\Routing\Route
     */
    public static function post($uri, $action = null)
    {
        return static::addRoute('post', $uri, $action);
    }

    /**
     * Register a new PUT route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Terablaze\Routing\Route
     */
    public static function put($uri, $action = null)
    {
        return static::addRoute('put', $uri, $action);
    }

    /**
     * Register a new PATCH route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Terablaze\Routing\Route
     */
    public static function patch($uri, $action = null)
    {
        return static::addRoute('patch', $uri, $action);
    }

    /**
     * Register a new DELETE route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Terablaze\Routing\Route
     */
    public static function delete($uri, $action = null)
    {
        return static::addRoute('delete', $uri, $action);
    }

    /**
     * Register a new OPTIONS route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Terablaze\Routing\Route
     */
    public static function options($uri, $action = null)
    {
        return static::addRoute('options', $uri, $action);
    }

    /**
     * Register a new route responding to all verbs.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Terablaze\Routing\Route
     */
    public static function any($uri, $action = null)
    {
        return static::addRoute(self::$verbs, $uri, $action);
    }

    /**
     * Register a new fallback route with the router.
     *
     * @param  array|string|callable|null  $action
     * @return \Terablaze\Routing\Route
     */
    public static function fallback($action)
    {
        $placeholder = 'fallbackPlaceholder';

        return static::addRoute('get', "{{$placeholder}}", $action);
    }

    /**
     * Create a redirect from one URI to another.
     *
     * @param  string  $uri
     * @param  string  $destination
     * @param  int  $status
     * @return \Terablaze\Routing\Route
     */
    public static function redirect($uri, $destination, int $status = 302)
    {
        return static::any($uri, function () use ($destination, $status) {
            return new RedirectResponse($destination, $status);
        });
    }

    /**
     * Create a permanent redirect from one URI to another.
     *
     * @param  string  $uri
     * @param  string  $destination
     * @return \Terablaze\Routing\Route
     */
    public static function permanentRedirect($uri, $destination)
    {
        return static::redirect($uri, $destination, 301);
    }

    /**
     * Register a new route that returns a view.
     *
     * @param  string  $uri
     * @param  string  $view
     * @param  array  $data
     * @param  int|array  $status
     * @param  array  $headers
     * @return \Terablaze\Routing\Route
     */
    public static function view($uri, $view, $data = [], $status = 200, array $headers = [])
    {
        return static::match(['get', 'head'], $uri, function () use ($view, $data, $status, $headers) {
            return Helpers::render($view, $data, $status, $headers);
        });
    }

    /**
     * Register a new route with the given verbs.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Terablaze\Routing\Route
     */
    public static function match($methods, $uri, $action = null)
    {
        return static::addRoute(array_map('strtolower', (array) $methods), $uri, $action);
    }

    /**
     * Add a route to the underlying route collection.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  array|callable|null  $action
     * @return \Terablaze\Routing\Route
     */
    public static function addRoute(string|array $methods, string $uri, $action)
    {
        $route = new self(Container::getContainer());
        $route->pattern = $uri;
        if (is_array($action)) {
            [$route->controller, $route->action] = $action;
        } elseif (is_callable($action)) {
            $route->callableRoute = $action;
        }
        $route->method = $methods;
        $route->router->addRoute(null, $route);
        return $route;
    }
}

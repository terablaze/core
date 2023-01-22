<?php

namespace Terablaze\Routing;

use Terablaze\Container\Container;
use Terablaze\Support\ArrayMethods;

/**
 * Class Route
 * @package Terablaze\Routing
 */
class Route
{
    private Container $container;

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
     * Route constructor.
     * @param int|string $name
     * @param callable|array<string, mixed> $route
     */
    public function __construct(Container $container, string $name = "", $route = [])
    {
        $this->container = $container;

        $this->name = (string)$name;
        $this->pattern = $route['pattern'] ?? '/';
        unset($route['pattern']);
        $this->controller = $route['controller'] ?? null;
        unset($route['controller']);
        $this->action = $route['action'] ?? null;
        unset($route['action']);
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

        $this->explicitlySetLocale = getExplicitlySetLocale();
        $this->currentLocale = getCurrentLocale();
        $this->localeType = getConfig('app.locale_type', 'path');
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
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
     * @return bool
     */
    public function isExpectsJson(): bool
    {
        return $this->expectsJson;
    }

    /**
     * @return array|mixed|string[]
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
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

    public static function get(string $path, $action = [])
    {
        $route = new self(Container::getContainer());
        $route->pattern = $path;
        if (is_array($action)) {
            if (is_string($action[0])) {
                $route->controller = $action[0];
                $route->action = $action[1];
            }
        }
        /** @var RouterInterface $router */
        $router = $route->container->get(RouterInterface::class);
        $router->addRoute($route->getName(), $route);
        return $route;
    }
}

<?php

namespace TeraBlaze\Routing;

use TeraBlaze\ArrayMethods as ArrayMethods;

/**
 * Class Route
 * @package TeraBlaze\Routing
 */
class Route
{
    /** @var string $name */
    public $name;

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
     * Route constructor.
     * @param int|string $name
     * @param callable|array<string, mixed> $route
     */
    public function __construct($name, $route)
    {
        $this->name = (string)$name;
        $this->pattern = $route['pattern'] ?? '/';
        unset($route['pattern']);
        $this->controller = $route['controller'] ?? null;
        unset($route['controller']);
        $this->action = $route['action'] ?? null;
        unset($route['action']);
        $this->method = ArrayMethods::wrap($route['method'] ?? '');
        unset($route['method']);
        $this->expectsJson = $route['expects_json'] ?? false;
        unset($route['expects_json']);
        $this->middlewares = $route['middlewares'] ?? [];
        unset($route['middlewares']);
        foreach ($route as $callable) {
            if (is_callable($callable)) {
                $this->callableRoute = $callable;
                continue;
            }
        }
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
    public function matches(string $method, string $path): bool
    {
        if (strtolower(reset($this->method)) === strtolower($method) && $this->pattern === $path) {
            return true;
        }
        $path = $this->normalizePath($path);

        $pattern = $this->normalizePath($this->pattern);

        preg_match_all("#" . Router::NAMED_ROUTE_MATCH . "|:([a-zA-Z0-9]+)#", $pattern, $keys);

        $keysToReplace = [];
        $keyPatterns = [];
        foreach ($keys[0] as $key) {
            $keysToReplace[] = "{$key}";
            $keyParts = explode(":", trim($key, "{}"));
            $keyPattern = $keyParts[1] ?? "any";
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
}

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

    /** @var string $path */
    public $path;

    /** @var string $action */
    public $action;

    /** @var string[]|null $method */
    public $method;

    /** @var string $controller */
    public $controller;

    /** @var bool $expectsJson */
    public $expectsJson;

    /** @var array<string|int, mixed> $parameters */
    public array $parameters = [];

    /** @var string[] $middlewares */
    public array $middlewares = [];

    /**
     * Route constructor.
     * @param int|string $name
     * @param array<string, mixed> $route
     */
    public function __construct($name, array $route = [])
    {
        $this->name = (string)$name;
        $this->path = $route['pattern'] ?? null;
        $this->controller = $route['controller'] ?? null;
        $this->action = $route['action'] ?? null;
        $this->method = is_array($route['method'] ?? []) ? $route['method'] ?? [] : [$route['method']];
        $this->expectsJson = $route['expects_json'] ?? false;
        $this->middlewares = $route['middlewares'] ?? [];
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
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @return array|string|null
     */
    public function getMethod()
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

    /**
     * @param string $url
     * @return bool
     */
    public function matches(string $url): bool
    {
        $url = trim($url, '/');
        $path = explode("#", $this->path);
        $pattern = $path[0];
        $path = explode("?", $pattern);
        $pattern = $path[0];

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
            return preg_match("#^{$pattern}$#", $url);
        }

        // normalize route pattern
        $pattern = preg_replace(Router::PATTERN_KEYS, Router::PATTERN_KEYS_REPLACEMENTS, $pattern);

        // check values
        preg_match_all("#^{$pattern}$#", $url, $values);

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
}

<?php

namespace TeraBlaze\Router\Generator;

use TeraBlaze\Collections\ArrayCollection;
use TeraBlaze\Router\Exception as Exception;
use TeraBlaze\Router\Exception\RouteNotFoundException;
use TeraBlaze\Router\Route\Route;
use TeraBlaze\Router\Router;

/**
 * Class UrlGenerator
 * @package TeraBlaze\Router
 *
 * handles url generation
 */
class UrlGenerator
{
    /**
     * Generates an absolute URL, e.g. "http://example.com/dir/file".
     */
    public const ABSOLUTE_URL = 0;

    /**
     * Generates an absolute path, e.g. "/dir/file".
     */
    public const ABSOLUTE_PATH = 1;

    /**
     * Generates a relative path based on the current request path, e.g. "../parent-file".
     *
     * @see UrlGenerator::getRelativePath()
     */
    public const RELATIVE_PATH = 2;

    /**
     * Generates a network path, e.g. "//example.com/dir/file".
     * Such reference reuses the current scheme but specifies the host.
     */
    public const NETWORK_PATH = 3;

    /** @var Route[] $routes */
    protected $routes;

    public function __construct($routes)
    {
        $this->routes = $routes;
    }

    /**
     * @param string $name
     * @param array $parameters
     * @param int $referenceType
     * @throws RouteNotFoundException
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        if (!isset($this->routes[$name])) {
            throw new RouteNotFoundException(sprintf("The named route: %s you are trying to reference does not exist", $name));
        }
        $route = $this->routes[$name];

        preg_match_all("#" . Router::NAMED_ROUTE_MATCH . "#", $route->getPath(), $keys);
        $params = new ArrayCollection($parameters);

        $url = $route->getPath();
        $urlAndAnchor = explode("#", $url, 2);
        $url = $urlAndAnchor[0];
        $anchor = isset($urlAndAnchor[1]) ? "#" . $urlAndAnchor[1] : "";
        if (sizeof($keys) && sizeof($keys[0]) && sizeof($keys[1])) {
            $keys = $keys[1];
            $resolvedKeyValue = [];
            foreach ($keys as $key) {
                // Parts of key name:match:default
                $keyDetails = explode(":", $key);
                // Name of the key
                $keyName = $keyDetails[0] ?? "";
                $keyPattern = $keyDetails[1] ?? "any";
                $keyMatch = in_array("#(:". $keyPattern . ")#", Router::PATTERN_KEYS) ? ":" .$keyPattern : $keyPattern;

                // Resolved acceptable match regex
                $keyMatch = preg_replace(Router::PATTERN_KEYS, Router::PATTERN_KEYS_REPLACEMENTS, $keyMatch);

                // Default value when not supplied un url parameters
                $keyDefault = $keyDetails[2] ?? "";

                // Supplied value from url parameters. Defaults to null
                $keyValue = $params->get($keyName);
                $requiredKeyNames[$keyName] = $keyName;
                $keyValue = urlencode($keyValue ?? $keyDefault);
                if (!empty($keyValue)){
                    $resolvedKeyValue[$keyName] = $keyValue;
                }
                $params->remove($keyName);
            }

            if ($diff = array_diff_key($requiredKeyNames, $resolvedKeyValue)) {
                throw new Exception\MissingParametersException(sprintf('Some mandatory parameters are missing ("%s") to generate a URL for route "%s".', implode('", "', array_keys($diff)), $name));
            }
            $url = str_replace($keys, array_keys($resolvedKeyValue), $url);
            $url = str_replace(array_keys($resolvedKeyValue), $resolvedKeyValue, $url);
            $pathArray = explode("/", $url);
            $pathArray = array_map(function ($part) {
                return trim($part, "{}");
            }, $pathArray);
            $url = implode("/", $pathArray);
        }
        $joiner = "?";
        if ($params->count() >= 1) {
            $queryString = http_build_query($params->toArray());
            if (strpos($url, '?') !== false) {
                $joiner = "&";
            }
            $url .= $joiner . $queryString . $anchor;
        }
        return $url;
    }
}
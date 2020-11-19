<?php

namespace TeraBlaze\Router\Generator;

use TeraBlaze\Collections\ArrayCollection;
use TeraBlaze\Container\Container;
use TeraBlaze\HttpBase\Request;
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
class UrlGenerator implements UrlGeneratorInterface
{
    /** @var Route[] $routes */
    protected $routes;

    /** @var Container $container */
    protected $container;

    /** @var Request $request */
    protected $request;
    /**
     * @var string|string[]|null
     */
    private $virtualLocation;

    public function __construct($routes)
    {
        $this->routes = $routes;
        $this->container = Container::getContainer();
        $this->request = Request::createFromGlobals();
        $scriptName = $this->request->getServerParams()['SCRIPT_NAME'];
        $this->virtualLocation = $this->container->hasParameter('virtualLocation') ?
            rtrim($this->container->getParameter('virtualLocation'), '/\\') :
            preg_replace('#[public]?[^/]*.php(.*)$#', '', $scriptName);
    }

    /**
     * {@inheritDoc}
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
                if (empty($keyName)) {
                    throw new Exception\MissingRouteParameterNameException('Route key must have a name');
                }
                $keyPattern = $keyDetails[1] ?? "any";
                $keyMatch = in_array("#(:". $keyPattern . ")#", Router::PATTERN_KEYS) ? ":" .$keyPattern : $keyPattern;

                // Resolved acceptable match regex
                $keyMatch = preg_replace(Router::PATTERN_KEYS, Router::PATTERN_KEYS_REPLACEMENTS, $keyMatch);

                // Default value when not supplied un url parameters
                $keyDefault = $keyDetails[2] ?? "";

                // Supplied value from url parameters. Defaults to null
                $keyValue = $params->get($keyName);
                $requiredKeyNames[$keyName] = $keyName;
                $keyValue = rawurlencode($keyValue ?? $keyDefault);
                if (!empty($keyValue)){
                    $resolvedKeyValue[$keyName] = $keyValue;
                }
                $params->remove($keyName);
            }

            if ($diff = array_diff_key($requiredKeyNames, $resolvedKeyValue)) {
                throw new Exception\MissingParametersException(sprintf('Some mandatory parameters are missing ("%s") to generate a URL for route "%s".', implode('", "', array_keys($diff)), $name));
            }
            $newKeys = array_map(function ($newKey) {
                return "{{$newKey}}";
            }, $keys);
            $keysToChange = array_map(function ($resolvedKey) {
                return "{{$resolvedKey}}";
            }, array_keys($resolvedKeyValue));
            $url = str_replace($newKeys, $keysToChange, $url);
            $url = str_replace($keysToChange, $resolvedKeyValue, $url);
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
        return $this->resolveReference($url, $referenceType);
    }


    /**
     * {@inheritDoc}
     */
    public function generateAsset(string $url, int $referenceType = self::ABSOLUTE_PATH): string
    {
        return $this->resolveReference('assets/' . $url, $referenceType);
    }

    private function resolveReference(string $url, string $referenceType): string
    {
        $port = $this->request->getUri()->getPort() ? ':' . $this->request->getUri()->getPort() : '';
        switch ($referenceType) {
            case self::ABSOLUTE_URL:
                return $this->request->getUri()->getScheme() . '://' .
                    $this->request->getUri()->getHost() . $port . $this->virtualLocation . $url;
            case self::ABSOLUTE_PATH:
                return $this->virtualLocation . $url;
            case self::NETWORK_PATH:
                return '//' . $this->request->getUri()->getHost() . $port . $this->virtualLocation . $url;
            default:
                return $url;
        }
    }
}

<?php

namespace TeraBlaze\Routing\Generator;

use TeraBlaze\Collection\ArrayCollection;
use TeraBlaze\Collection\Exceptions\TypeException;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\Routing\Exception\InvalidParameterException;
use TeraBlaze\Routing\Exception\MissingParametersException;
use TeraBlaze\Routing\Exception\MissingRouteParameterNameException;
use TeraBlaze\Routing\Exception\RouteNotFoundException;
use TeraBlaze\Routing\Route;
use TeraBlaze\Routing\Router;
use TeraBlaze\Routing\RouterInterface;
use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Support\StringMethods;

/**
 * Class UrlGenerator
 * @package TeraBlaze\Routing
 *
 * handles url generation
 */
class UrlGenerator implements UrlGeneratorInterface
{
    protected RouterInterface $router;

    /** @var Route[] $routes */
    protected array $routes;

    /** @var Request $request */
    protected $request;

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
    private $cachedRoot;
    private $forcedRoot;
    private $forceScheme;

    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->routes = $router->getRoutes();
        $this->request = $router->getCurrentRequest();

        $this->explicitlySetLocale = getExplicitlySetLocale();
        $this->currentLocale = getCurrentLocale();
        $this->localeType = getConfig('app.locale_type', 'path');
    }

    /**
     * @param string $name
     * @param array<string, string> $parameters
     * @param int $referenceType
     * @param string|null $locale
     * @return string
     * @throws MissingParametersException
     * @throws MissingRouteParameterNameException
     * @throws RouteNotFoundException
     * @throws TypeException|InvalidParameterException
     *
     * {@inheritDoc}
     */
    public function generate(
        string $name,
        array $parameters = [],
        int $referenceType = self::ABSOLUTE_PATH,
        ?string $locale = null
    ): string {
        if ($this->isValidUrl($name)) {
            return $name;
        }
        if (empty($name)) {
            return $this->resolveReference($this->getLocaledUrl('', $locale), $referenceType);
        }
        if (!isset($this->routes[$name])) {
            throw new RouteNotFoundException(
                sprintf("The named route: %s you are trying to reference does not exist", $name)
            );
        }
        $route = $this->routes[$name];

        preg_match_all("#" . Router::NAMED_ROUTE_MATCH . "#", $route->getPattern(), $keys);
        $params = new ArrayCollection($parameters);

        $url = $route->getPattern();
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
                    throw new MissingRouteParameterNameException('Route key must have a name');
                }
                $keyPattern = $keyDetails[1] ?? "any";
                $keyMatch = in_array(
                    "#(:" . $keyPattern . ")#",
                    Router::PATTERN_KEYS
                ) ? ":" . $keyPattern : $keyPattern;

                // Resolved acceptable match regex
                $keyMatch = preg_replace(Router::PATTERN_KEYS, Router::PATTERN_KEYS_REPLACEMENTS, $keyMatch);

                // Default value when not supplied un url parameters
                $keyDefault = $keyDetails[2] ?? "";

                // Supplied value from url parameters. Defaults to null
                $keyValue = $params->get($keyName);
                $requiredKeyNames[$keyName] = $keyName;
                $keyValue = rawurlencode($keyValue ?? $keyDefault);
                if (!preg_match("#^$keyMatch$#", $keyValue)) {
                    throw new InvalidParameterException(
                        sprintf('The supplied value does for route parameter "%s" is of an incompatible type.
                        Pattern "%s" allowed, but "%s" supplied.', $keyName, $keyMatch, $keyValue)
                    );
                }
                if (!empty($keyValue)) {
                    $resolvedKeyValue[$keyName] = $keyValue;
                }
                $params->remove($keyName);
            }

            if ($diff = array_diff_key($requiredKeyNames, $resolvedKeyValue)) {
                throw new MissingParametersException(
                    sprintf(
                        'Some mandatory parameters are missing ("%s") to generate a URL for route "%s".',
                        implode('", "', array_keys($diff)),
                        $name
                    )
                );
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
        return $this->resolveReference($this->getLocaledUrl($url, $locale), $referenceType);
    }


    /**
     * {@inheritDoc}
     */
    public function generateAsset(string $uri, int $referenceType = self::ABSOLUTE_PATH): string
    {
        if ($this->isValidUrl($uri)) {
            return $uri;
        }
        return $this->resolveReference('assets/' . $uri, $referenceType);
    }

    /**
     * Get the current URL for the request.
     *
     * @return string
     */
    public function current(): string
    {
        return $this->to($this->request->getPathInfo());
    }

    /**
     * Get the URL for the previous request.
     *
     * @param  mixed  $fallback
     * @return string
     */
    public function previous($fallback = false): string
    {
        $referrer = $this->request->getHeaderLine('referer');

        $url = $this->to($referrer);

        if ($url) {
            return $url;
        }
        if ($fallback) {
            return $this->to($fallback);
        }

        return $this->to('/');
    }

    /**
     * Generate an absolute URL to the given path.
     *
     * @param  string  $path
     * @param  mixed  $extra
     * @param  bool|null  $secure
     * @return string
     */
    public function to($path, $extra = [], $secure = null): string
    {
        // First we will check if the URL is already a valid URL. If it is we will not
        // try to generate a new one but will simply return the URL as is, which is
        // convenient since developers do not always have to check if it's valid.
        if ($this->isValidUrl($path)) {
            return $path;
        }

        $tail = implode('/', array_map(
                'rawurlencode', ArrayMethods::wrap($extra))
        );

        // Once we have the scheme we will compile the "tail" by collapsing the values
        // into a single string delimited by slashes. This just makes it convenient
        // for passing the array of parameters to this URL as a list of segments.
        $root = $this->formatRoot($this->formatScheme($secure));

        [$path, $query] = $this->extractQueryString($path);

        return $this->format(
                $root, '/'.trim($path.'/'.$tail, '/')
            ).$query;
    }

    /**
     * Format the given URL segments into a single URL.
     *
     * @param  string  $root
     * @param  string  $path
     * @return string
     */
    public function format($root, $path): string
    {
        $path = '/'.trim($path, '/');

        return trim($root.$path, '/');
    }

    /**
     * Extract the query string from the given path.
     *
     * @param  string  $path
     * @return array
     */
    protected function extractQueryString($path)
    {
        if (($queryPosition = strpos($path, '?')) !== false) {
            return [
                substr($path, 0, $queryPosition),
                substr($path, $queryPosition),
            ];
        }

        return [$path, ''];
    }

    /**
     * Get the default scheme for a raw URL.
     *
     * @param  bool|null  $secure
     * @return string
     */
    public function formatScheme($secure = null): string
    {
        if (! is_null($secure)) {
            return $secure ? 'https://' : 'http://';
        }

        if (is_null($this->cachedScheme)) {
            $this->cachedScheme = $this->forceScheme ?: $this->request->getScheme().'://';
        }

        return $this->cachedScheme;
    }

    /**
     * Get the base URL for the request.
     *
     * @param  string  $scheme
     * @param  string|null  $root
     * @return string
     */
    public function formatRoot($scheme, $root = null): string
    {
        if (is_null($root)) {
            if (is_null($this->cachedRoot)) {
                $this->cachedRoot = $this->forcedRoot ?: $this->request->getBaseUrl();
            }
            $root = $this->cachedRoot;
        }

        $start = StringMethods::startsWith($root, 'http://') ? 'http://' : 'https://';

        return preg_replace('~'.$start.'~', $scheme, $root, 1);
    }

    private function resolveReference(string $url, string $referenceType): string
    {
        $port = $this->request->getUri()->getPort() ? ':' . $this->request->getUri()->getPort() : '';
        $path = $this->request->getBaseUrl() . '/' . $url;
        $hostPortPath = $this->request->getUri()->getHost() . $port . $path;
        switch ($referenceType) {
            case self::ABSOLUTE_URL:
                return $this->request->getUri()->getScheme() . '://' . $hostPortPath;
            case self::ABSOLUTE_PATH:
                return $path;
            case self::NETWORK_PATH:
                return '//' . $hostPortPath;
            default:
                return $url;
        }
    }

    /**
     * Determine if the given path is a valid URL.
     *
     * @param  string  $path
     * @return bool
     */
    private function isValidUrl($path)
    {
        if (! preg_match('~^(#|//|https?://|(mailto|tel|sms):)~', $path)) {
            return filter_var($path, FILTER_VALIDATE_URL) !== false;
        }

        return true;
    }

    private function getLocaledUrl(string $path, ?string $locale = null): string
    {
        $pathLocalePrefix = "";
        if ($this->localeType === 'path') {
            if ($locale) {
                $pathLocalePrefix = "$locale/";
            } elseif ($this->explicitlySetLocale !== "") {
                $pathLocalePrefix = "$this->currentLocale/";
            }
        }
        return $pathLocalePrefix . $path;
    }
}

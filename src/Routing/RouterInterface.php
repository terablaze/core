<?php

namespace TeraBlaze\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use TeraBlaze\Collection\Exceptions\TypeException;
use TeraBlaze\Routing\Exception\ImplementationException;
use TeraBlaze\Routing\Exception\MissingParametersException;
use TeraBlaze\Routing\Exception\RouteNotFoundException;
use TeraBlaze\Routing\Generator\UrlGeneratorInterface;

/**
 * Interface RouterInterface
 * @package TeraBlaze\Routing
 */
interface RouterInterface
{
    public const SERVICE_ALIAS = "router";

    public const NAMED_ROUTE_MATCH = "{(\w[\w:.,\-'\"{}^$+*?\#\[\]()\\\\\ ]+)}";
    public const PATTERN_KEYS =
        ["#(:any)#", "#(:alpha)#", "#(:alphabet)#", "#(:num)#", "#(:numeric)#", "#(:mention)#"];
    public const PATTERN_KEYS_REPLACEMENTS =
        ["([^/]+)", "([a-zA-Z]+)", "([a-zA-Z]+)", "([\d]+)", "([\d]+)", "(@[a-zA-Z0-9-_]+)"];

    /**
     * @return ServerRequestInterface
     */
    public function getCurrentRequest(): ServerRequestInterface;

    /**
     * @return Route
     */
    public function getCurrentRoute(): Route;

    public function addRoutes(array $routes, array $config = [], int $nestLevel = 0): void;

    public function addRoute(string $name, Route $route): RouterInterface;

    public function removeRoute($route): RouterInterface;

    /**
     * @return Route[]
     */
    public function getRoutes(): array;

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws ImplementationException
     * @throws ReflectionException
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface;

    /**
     * @param string $name
     * @param array<string, mixed> $parameters
     * @param int $referenceType
     * @return string
     * @throws MissingParametersException
     * @throws ReflectionException
     * @throws RouteNotFoundException
     * @throws TypeException
     */
    public function generate(
        string $name,
        array $parameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): string;

    /**
     * @return UrlGeneratorInterface
     * @throws ReflectionException
     */
    public function getGenerator(): UrlGeneratorInterface;
}

<?php

namespace Terablaze\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use Terablaze\Collection\Exceptions\TypeException;
use Terablaze\HttpBase\Request;
use Terablaze\HttpBase\Response;
use Terablaze\Routing\Exception\ImplementationException;
use Terablaze\Routing\Exception\MissingParametersException;
use Terablaze\Routing\Exception\RouteNotFoundException;
use Terablaze\Routing\Generator\UrlGeneratorInterface;

/**
 * Interface RouterInterface
 * @package Terablaze\Routing
 */
interface RouterInterface
{
    public const SERVICE_ALIAS = "router";

    public const NAMED_ROUTE_MATCH = "{(\w[\w:.,\-'\"{}^$+*?\#\[\]()\\\\\ ]+)}";
    public const PATTERN_KEYS =
        ["#(:any)#", "#(:alpha)#", "#(:alphabet)#", "#(:num)#", "#(:numeric)#", "#(:int)#", "#(:integer)#"];
    public const PATTERN_KEYS_REPLACEMENTS =
        ["([^/]+)", "([a-zA-Z]+)", "([a-zA-Z]+)", "([\d]+)", "([\d]+)", "([\d]+)", "([\d]+)"];

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

    public function removeRoute($routeName): RouterInterface;

    public function syncRouteName(Route $route): RouterInterface;

    /**
     * @return Route[]
     */
    public function getRoutes(): array;

    public function getRoute(string $routeName): ?Route;

    public function hasRoute(string $routeName): bool;

    /**
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface|Request|ResponseInterface|Response
     */
    public function dispatch(ServerRequestInterface $request);

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

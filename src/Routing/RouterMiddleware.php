<?php

namespace TeraBlaze\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TeraBlaze\Config\Config;
use TeraBlaze\Config\Driver\DriverInterface;
use TeraBlaze\Container\Container;

class RouterMiddleware implements MiddlewareInterface
{
    private Container $container;
    private Router $router;

    public function __construct(Container $container, Router $router)
    {
        $this->container = $container;
        $this->router = $router;
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routes = loadConfigArray('routes');

        // add defined routes
        if (!empty($routes) && is_array($routes)) {
            $this->router->addRoutes($routes);
        }

        return $this->router->dispatch($request);
    }
}

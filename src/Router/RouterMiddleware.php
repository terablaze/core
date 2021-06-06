<?php

namespace TeraBlaze\Router;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TeraBlaze\Configuration\Driver\DriverInterface;
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
        /** @var DriverInterface $configuration */
        $configuration = $this->container->get('configuration');

        $routes = $configuration->parseArray("routes");

        // add defined routes
        if (!empty($routes) && is_array($routes)) {
            $this->router->addRoutes($routes);
        }

        return $this->router->dispatch($request);
    }
}

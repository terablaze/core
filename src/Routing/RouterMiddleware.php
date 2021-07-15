<?php

namespace TeraBlaze\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RouterMiddleware implements MiddlewareInterface
{
    private Router $router;

    public function __construct(Router $router)
    {
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

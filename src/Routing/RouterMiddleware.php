<?php

namespace TeraBlaze\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Kernel\Handler;
use TeraBlaze\HttpBase\Request;

class RouterMiddleware implements MiddlewareInterface
{
    private Container $container;
    private Router $router;

    public function __construct(Container $container, RouterInterface $router)
    {
        $this->container = $container;
        $this->router = $router;
    }

    /**
     * @param ServerRequestInterface|Request $request
     * @param RequestHandlerInterface|Handler $handler
     * @return ResponseInterface
     * @throws Exception\ImplementationException
     * @throws \ReflectionException
     * @throws \TeraBlaze\Container\Exception\ContainerException
     * @throws \TeraBlaze\Container\Exception\ParameterNotFoundException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $dispatchResult = $this->router->dispatch($request);
        if ($dispatchResult instanceof ResponseInterface) {
            return $dispatchResult;
        }

        if ($dispatchResult instanceof ServerRequestInterface) {
            /** @var Route $route */
            $route = $dispatchResult->getAttribute('route');
            $middlewares = $route->getMiddlewares();

            if (empty($middlewares)) {
                return $handler->handle($dispatchResult);
            }

            array_walk($middlewares, function (&$middleware) {
                $middleware = $this->container->make($middleware);
            });

            $handler->addMiddleware($middlewares);

            return $handler->handle($dispatchResult);
        }

        throw new \Exception('Unable to resolve request');
    }
}

<?php

namespace TeraBlaze\Core\Kernel;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use TeraBlaze\Container\Container;
use TeraBlaze\Routing\RouterMiddleware;
use TeraBlaze\Support\ArrayMethods;

class Handler implements RequestHandlerInterface
{
    /** @var array $queue */
    private $queue;
    /** @var callable $resolver */
    private $resolver;
    /** @var ContainerInterface|Container $container */
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container, $queue, callable $resolver = null)
    {
        $this->container = $container;
        $this->queue = ArrayMethods::wrap($queue);

        if (empty($this->queue)) {
            throw new InvalidArgumentException('Middleware queue cannot be empty');
        }

        if ($resolver === null) {
            $resolver = function ($entry) {
                return $entry;
            };
        }

        $this->resolver = $resolver;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->container->removeService('request');
        $this->container->registerServiceInstance('request', $request);
        $this->container->setAlias(ServerRequestInterface::class, 'request');
        $entry = current($this->queue);
        $middleware = call_user_func($this->resolver, $entry);
        next($this->queue);

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $this);
        }

        if ($middleware instanceof RequestHandlerInterface) {
            return $middleware->handle($request);
        }

        if (is_callable($middleware)) {
            return $middleware($request, $this);
        }

        $middlewareString = is_object($middleware) ? get_class($middleware) : $middleware;

        throw new RuntimeException(
            sprintf(
                'Invalid middleware queue entry: %s. Middleware must either be callable or implement %s.',
                $middlewareString,
                MiddlewareInterface::class
            )
        );
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handle($request);
    }
}

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
use function _PHPStan_76800bfb5\RingCentral\Psr7\str;

class Handler implements RequestHandlerInterface
{
    /** @var array $queue */
    private $queue;
    /** @var callable $resolver */
    private $resolver;
    private $shiftedQueue;
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
        $this->shiftedQueue[] = array_shift($this->queue);

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $this);
        }

        if ($middleware instanceof RequestHandlerInterface) {
            return $middleware->handle($request);
        }

        if (is_callable($middleware)) {
            return $middleware($request, $this);
        }

        $middlewareString = is_object($middleware) ? get_class($middleware) : (string)$middleware;

        throw new RuntimeException(
            sprintf(
                'Invalid middleware queue entry: %s. Middleware must either be callable or implement %s.',
                $middlewareString,
                MiddlewareInterface::class
            )
        );
    }

    public function addMiddleware($middleware)
    {
        array_splice($this->queue, -1, 0, $middleware);
    }

    public function getShiftedQueue(): array
    {
        return $this->shiftedQueue;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handle($request);
    }
}

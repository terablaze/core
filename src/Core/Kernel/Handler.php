<?php

namespace TeraBlaze\Core\Kernel;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

class Handler implements RequestHandlerInterface
{
    /** @var array $queue */
    private $queue;
    /** @var callable $resolver */
    private $resolver;

    public function __construct($queue, callable $resolver = null)
    {
        if (!is_array($queue)) {
            $queue = [$queue];
        }

        $this->queue = $queue;

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

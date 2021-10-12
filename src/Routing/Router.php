<?php

namespace TeraBlaze\Routing;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use TeraBlaze\Collection\Exceptions\TypeException;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\Exception\ContainerException;
use TeraBlaze\Container\Exception\ParameterNotFoundException;
use TeraBlaze\Controller\ControllerInterface;
use TeraBlaze\ErrorHandler\Exception\Http\MethodNotAllowedHttpException;
use TeraBlaze\ErrorHandler\Exception\Http\NotFoundHttpException;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\Routing\Events\PostControllerEvent;
use TeraBlaze\Routing\Events\PostDispatchEvent;
use TeraBlaze\Routing\Events\PreControllerEvent;
use TeraBlaze\Routing\Events\PreDispatchEvent;
use TeraBlaze\Routing\Exception\ImplementationException;
use TeraBlaze\Routing\Exception\MissingParametersException;
use TeraBlaze\Routing\Exception\RouteNotFoundException;
use TeraBlaze\Routing\Generator\UrlGenerator;
use TeraBlaze\Routing\Generator\UrlGeneratorInterface;

/**
 * Class Routing
 * @package TeraBlaze\Routing
 *
 * handles url routing
 */
class Router implements RouterInterface
{
    /** @var Container $container */
    protected $container;

    /**
     * @var Route[]
     */
    protected $routes = [];

    /**
     * @var Route
     */
    protected Route $current;

    /**
     * @var ServerRequestInterface|Request $currentRequest
     */
    protected ServerRequestInterface $currentRequest;

    private EventDispatcherInterface $dispatcher;

    public function __construct(
        Container $container,
        EventDispatcherInterface $dispatcher
    ) {
        $this->container = $container;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return ServerRequestInterface|Request
     */
    public function getCurrentRequest(): ServerRequestInterface
    {
        return $this->currentRequest ?? kernel()->getCurrentRequest();
    }

    /**
     * @return Route
     */
    public function getCurrentRoute(): Route
    {
        return $this->match(kernel()->getCurrentRequest()->getPathInfo());
    }

    /**
     * @param array<string|int, array> $routes
     * @param array<string, mixed> $config
     * @param int $nestLevel
     */
    public function addRoutes(array $routes, array $config = [], int $nestLevel = 0): void
    {
        foreach ($routes as $name => $route) {
            if (
                !isset($route['pattern']) &&
                (isset($route['@group']) || in_array('@group', $route))
            ) {
                $groupConfig['prefix'] = ($config['prefix'] ?? '') . ($route['@prefix'] ?? '');
                $groupConfig['name_prefix'] = ($config['name_prefix'] ?? '') . ($route['@name_prefix'] ?? '');
                $groupConfig['middlewares'] = array_merge(
                    $config['middlewares'] ?? [],
                    $route['@middlewares'] ?? []
                );
                $groupConfig['expects_json'] = $route['@expects_json'] ?? $config['expects_json'] ?? false;
                $nestLevel++;
                $this->addRoutes($route['@routes'] ?? [], $groupConfig, $nestLevel);
                continue;
            }
            $name = ($config['name_prefix'] ?? '') . $name;
            if (!isset($route['pattern'])) {
                continue;
            }
            $route['pattern'] = ($config['prefix'] ?? '') . $route['pattern'] ?? '';
            $route['expects_json'] = $route['expects_json'] ?? $config['expects_json'] ?? false;
            $route['middlewares'] = $route['middlewares'] ?? $config['middlewares'] ?? [];
            $this->addRoute($name, new Route($name, $route));
        }
        if ($nestLevel > 0) {
            $nestLevel--;
        }
    }

    /**
     * @param string $name
     * @param Route $route
     * @return self
     */
    public function addRoute(string $name, Route $route): Router
    {
        $this->routes[$name] = $route;
        return $this;
    }

    /**
     * @param int|string $route
     * @return $this
     */
    public function removeRoute($route): Router
    {
        foreach ($this->routes as $name => $stored) {
            if ($name == $route) {
                unset($this->routes[$name]);
            }
        }
        return $this;
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getRoute(string $routeName): ?Route
    {
        return $this->routes[$routeName] ?? null;
    }

    public function hasRoute(string $routeName): bool
    {
        return isset($this->routes[$routeName]);
    }

    /**
     * @param ServerRequestInterface|Request $request
     * @return ServerRequestInterface|Request|ResponseInterface|Response
     * @throws ContainerException
     * @throws ImplementationException
     * @throws ParameterNotFoundException
     * @throws ReflectionException
     */
    public function dispatch(ServerRequestInterface $request)
    {
        $this->currentRequest = $request;
        $event = new PreDispatchEvent($this, $request);
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $this->dispatcher->dispatch($event);
        if ($event->hasResponse()) {
            return $event->getResponse();
        }

        $requestMethod = $request->getMethod();

        if ($this->match($path)) {
            $request = $request->withAttribute('route', $this->current);
            $method = $this->current->getCleanedMethod();

            if (!in_array(strtolower($requestMethod), $method) && !empty($method)) {
                throw new MethodNotAllowedHttpException(
                    $method,
                    "Request method \"{$request->getMethod()}\" not implemented for this endpoint"
                );
            }

            /** @var Request $request */
            $request = $request->setExpectsJson($this->current->isExpectsJson());
            if ($request->expectsJson()) {
                if ($request->hasFlash()) {
                    $request->getFlash()->reflash();
                }
            }

            $event = new PostDispatchEvent($this, $request);
            $this->dispatcher->dispatch($event);
            if ($event->hasResponse()) {
                return $event->getResponse();
            }

            return $request;
        }

        throw new NotFoundHttpException(sprintf('No route found for : "%s"', $path));
    }

    private function match(string $path): ?Route
    {
        if (isset($this->current)) {
            return $this->current;
        }
        foreach ($this->routes as $route) {
            if ($route->matches($path)) {
                return $this->current = $route;
            }
        }

        return null;
    }

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
    ): string {
        return $this->getGenerator()->generate($name, $parameters, $referenceType);
    }

    /**
     * @return UrlGeneratorInterface
     * @throws ReflectionException
     */
    public function getGenerator(): UrlGeneratorInterface
    {
        if (!$this->container->has(UrlGenerator::class) && !$this->container->has(UrlGeneratorInterface::class)) {
            $this->container->registerService(UrlGeneratorInterface::class, [
                'class' => UrlGenerator::class,
                'arguments' => [$this]
            ]);
        }
        return $this->container->get(UrlGenerator::class);
    }
}

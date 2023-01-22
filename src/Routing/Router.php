<?php

namespace Terablaze\Routing;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use Terablaze\Collection\ArrayCollection;
use Terablaze\Collection\Exceptions\TypeException;
use Terablaze\Container\Container;
use Terablaze\Container\Exception\ContainerException;
use Terablaze\Container\Exception\ParameterNotFoundException;
use Terablaze\Core\Kernel\KernelInterface;
use Terablaze\ErrorHandler\Exception\Http\MethodNotAllowedHttpException;
use Terablaze\ErrorHandler\Exception\Http\NotFoundHttpException;
use Terablaze\HttpBase\Request;
use Terablaze\HttpBase\Response;
use Terablaze\Routing\Events\PostDispatchEvent;
use Terablaze\Routing\Events\PreDispatchEvent;
use Terablaze\Routing\Exception\ImplementationException;
use Terablaze\Routing\Exception\MissingParametersException;
use Terablaze\Routing\Exception\RouteNotFoundException;
use Terablaze\Routing\Generator\UrlGenerator;
use Terablaze\Routing\Generator\UrlGeneratorInterface;
use Terablaze\Support\ArrayMethods;
use Terablaze\Support\StringMethods;

/**
 * Class Routing
 * @package Terablaze\Routing
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
    /**
     * @var mixed|object
     */
    private KernelInterface $kernel;

    public function __construct(
        Container                $container,
        EventDispatcherInterface $dispatcher
    )
    {
        $this->container = $container;
        $this->dispatcher = $dispatcher;

        /** @var KernelInterface $kernel */
        $this->kernel = $this->container->get(KernelInterface::class);
    }

    /**
     * @return ServerRequestInterface|Request
     */
    public function getCurrentRequest(): ServerRequestInterface
    {
        return $this->currentRequest ?? $this->kernel->getCurrentRequest();
    }

    /**
     * @return Route
     */
    public function getCurrentRoute(): Route
    {
        return $this->match(
            $this->kernel->getCurrentRequest()->getPathInfo(),
            $this->kernel->getCurrentRequest()->getMethod()
        );
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
                    ArrayMethods::wrap($config['middlewares'] ?? $config['middleware'] ?? []),
                    ArrayMethods::wrap($route['@middlewares'] ?? $route['@middleware'] ?? [])
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
            $routeMiddlewares = ArrayMethods::wrap($route['middlewares'] ?? $route['middleware'] ?? []);
            $configMiddlewares = ArrayMethods::wrap($config['middlewares'] ?? $config['middleware'] ?? []);
            $route['middlewares'] = array_merge($routeMiddlewares, $configMiddlewares);
            $this->addRoute($name, new Route($this->container, $name, $route));
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

        if ($this->match($path, $request->getMethod())) {
            $request = $request->withAttribute('route', $this->current);

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

    private function match(string $path, string $method): ?Route
    {
        if (isset($this->current)) {
            return $this->current;
        }
        $pathMatchingRoutes = [];
        foreach ($this->routes as $route) {
            if ($route->matches($path)) {
                $pathMatchingRoutes[] = $route;

                $routeMethods = $route->getCleanedMethod();

                if (in_array(strtolower($method), $routeMethods) || empty($routeMethods)) {
                    $this->current = $route;
                    return $this->current;
                }
            }
        }
        if (!empty($pathMatchingRoutes)) {
            $this->raiseInvalidMethod($pathMatchingRoutes, $method);
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
        array  $parameters = [],
        int    $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): string
    {
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

    /**
     * @param array $pathMatchingRoutes
     * @param string $method
     * @return mixed
     * @throws \Terablaze\Collection\Exceptions\InvalidTypeException
     */
    private function raiseInvalidMethod(array $pathMatchingRoutes, string $method)
    {
        $allowedMethods = [];
        foreach ($pathMatchingRoutes as $pathMatchingRoute) {
            $allowedMethods = array_merge($allowedMethods, $pathMatchingRoute->getMethod());
        }
        $allowedMethods = (new ArrayCollection($allowedMethods))->unique()
            ->map(fn($method) => StringMethods::upper($method))->toArray();
        $allowedMethodsString = implode(", ", $allowedMethods);
        throw new MethodNotAllowedHttpException(
            $allowedMethods,
            "Request method \"{$method}\" not implemented for this endpoint, only \"$allowedMethodsString\" allowed"
        );
    }
}

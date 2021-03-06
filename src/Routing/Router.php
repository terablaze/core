<?php

namespace TeraBlaze\Routing;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use TeraBlaze\ArrayMethods;
use TeraBlaze\Collection\Exceptions\TypeException;
use TeraBlaze\Container\Container;
use TeraBlaze\Controller\ControllerInterface;
use TeraBlaze\ErrorHandler\Exception\Http\MethodNotAllowedHttpException;
use TeraBlaze\ErrorHandler\Exception\Http\NotFoundHttpException;
use TeraBlaze\Events\Events;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\Inspector;
use TeraBlaze\Routing\Events\PostBeforeHookEvent;
use TeraBlaze\Routing\Events\PostControllerEvent;
use TeraBlaze\Routing\Events\PostDispatchEvent;
use TeraBlaze\Routing\Events\PreBeforeHookEvent;
use TeraBlaze\Routing\Events\PreControllerEvent;
use TeraBlaze\Routing\Events\PreInitializeEvent;
use TeraBlaze\Routing\Events\PreDispatchEvent;
use TeraBlaze\Routing\Exception\ImplementationException;
use TeraBlaze\Routing\Exception\MissingParametersException;
use TeraBlaze\Routing\Exception\RouteNotFoundException;
use TeraBlaze\Routing\Generator\UrlGenerator;
use TeraBlaze\Routing\Generator\UrlGeneratorInterface;
use TeraBlaze\Routing\Route;

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
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $extension;

    /**
     * @var string
     */
    protected $controller;

    /**
     * @var string
     */
    protected $action;

    /**
     * @var string
     */
    protected $method;

    /**
     * @var Route[]
     */
    protected $routes = [];

    /**
     * @var Route
     */
    protected $current;

    /**
     * @var Request
     */
    protected $currentRequest;

    /**
     * @return Request
     */
    public function getCurrentRequest(): ServerRequestInterface
    {
        return $this->currentRequest;
    }

    /**
     * @return Route
     */
    public function getCurrentRoute(): Route
    {
        return $this->current;
    }

    private EventDispatcherInterface $dispatcher;

    public function __construct(
        Container $container,
        EventDispatcherInterface $dispatcher
    ) {
        $this->container = $container;
        $this->dispatcher = $dispatcher;
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

    // /**
    //  * @return array
    //  */
    // public function getRoutes()
    // {
    //     $list = array();

    //     foreach ($this->routes as $route) {
    //         $list[$route->path] = get_class($route);
    //     }

    //     return $list;
    // }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $controller
     * @param string $action
     * @param array<string|int, mixed> $parameters
     * @param string $method
     * @return ResponseInterface
     * @throws ImplementationException
     * @throws ReflectionException
     */
    protected function pass(
        ServerRequestInterface $request,
        string $controller,
        string $action,
        array $parameters = array(),
        string $method = ''
    ): ResponseInterface {
        $event = new PreControllerEvent($this, $request, $controller);
        $controller = $event->getController();
        Events::fire("terablaze.router.controller.before", array($controller, $parameters));

        $className = ucfirst($controller);
        $this->controller = $controller;
        $this->action = $action;
        $this->method = $method;

        if (!class_exists($className)) {
            throw new NotFoundHttpException("Controller '{$className}' not found");
        }

        if (!$this->container->has($className)) {
            $this->container->registerService($className, ['class' => $className]);
        }

        $controllerInstance = $this->container->get($className);
        if ($controllerInstance instanceof ControllerInterface) {
            $controllerInstance->setContainer($this->container);
        }

        $event = new PostControllerEvent($this, $request, $controllerInstance);
        $controllerInstance = $event->getControllerInstance();
        Events::fire("terablaze.router.controller.after", array($controller, $parameters));

        if (!method_exists($controllerInstance, $action)) {
            throw new NotFoundHttpException("Action '{$action}' not found");
        }

        $inspector = new Inspector($controllerInstance);
        $methodMeta = $inspector->getMethodMeta($action);

        if (!empty($methodMeta["@protected"]) || !empty($methodMeta["@private"])) {
            throw new NotFoundHttpException("Action '{$action}' not publicly accessible");
        }

        $hooks = function ($meta, $type) use ($inspector, $controllerInstance) {
            if (isset($meta[$type])) {
                $run = [];

                foreach ($meta[$type] as $method) {
                    $hookMeta = $inspector->getMethodMeta($method);

                    if (in_array($method, $run) && !empty($hookMeta["@once"])) {
                        continue;
                    }

                    $result = $controllerInstance->$method();
                    $run[] = $method;
                    return $result;
                }
            }
        };

        $event = new PreBeforeHookEvent($action, $parameters);
        $this->dispatcher->dispatch($event);
        Events::fire("terablaze.router.beforehooks.before", array($action, $parameters));

        $result = $hooks($methodMeta, "@before");

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        $event = new PostBeforeHookEvent($action, $parameters);
        $this->dispatcher->dispatch($event);
        Events::fire("terablaze.router.beforehooks.after", array($action, $parameters));


        Events::fire("terablaze.router.action.before", array($action, $parameters));

        $reflectionMethod = new \ReflectionMethod($controllerInstance, $action);
        $reflectionParameters = $reflectionMethod->getParameters();
        if (!empty($reflectionParameters) && is_object($firstArgument = $reflectionParameters[0])) {
            $reflectionClass = $firstArgument->getType();
            $reflectionClassName = is_null($reflectionClass) ? "" : $reflectionClass->getName();
            if ($reflectionClassName === Request::class) {
                array_unshift($parameters, $request);
            }
        }
        $response = call_user_func_array([
            $controllerInstance,
            $action
        ], $parameters);

        if (is_null($response)) {
            throw new ImplementationException(
                "Result of {$className}::{$action}() is either empty or null, " .
                "ensure the controller's action {$className}::{$action}() " .
                "is properly implemented and returns an instance of " . ResponseInterface::class
            );
        }

        if (!$response instanceof ResponseInterface) {
            throw new ImplementationException(
                "Result of {$className}::{$action}() is of type " . gettype($response) .
                ", ensure the controller's action {$className}::{$action}() " .
                "returns an instance of " . ResponseInterface::class
            );
        }

        Events::fire("terablaze.router.action.after", array($action, $parameters));
        Events::fire("terablaze.router.afterhooks.before", array($action, $parameters));

        $hooks($methodMeta, "@after");

        Events::fire("terablaze.router.afterhooks.after", array($action, $parameters));

        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws ImplementationException
     * @throws ReflectionException
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $this->currentRequest = $request;
        $event = new PreDispatchEvent($this, $request);
        $this->dispatcher->dispatch($event);
        if ($event->hasResponse()) {
            return $event->getResponse();
        }
        Events::fire("terablaze.router.dispatch.before", array($request->getPathInfo()));

        $request = $event->getRequest();

        $url = $request->getPathInfo();
        $parameters = array();
        $controller = '';
        $action = '';

        $requestMethod = $request->getMethod();

        foreach ($this->routes as $route) {
            $matches = $route->matches($url);
            if ($matches) {
                $this->current = $route;
                $controller = $route->getController();
                $action = $route->getAction();
                $parameters = $route->getParameters();
                $method = $route->getMethod();
                /** @var Request $request */
                $request = $request->setExpectsJson($route->isExpectsJson());

                $method = array_map(function ($methodItem) {
                    return strtolower($methodItem);
                }, $method);

                $method = ArrayMethods::clean($method);

                if (!in_array(strtolower($requestMethod), $method) && !empty($method)) {
                    throw new MethodNotAllowedHttpException(
                        $method,
                        "Request method \"{$request->getMethod()}\" not implemented for this endpoint"
                    );
                }

                $event = new PostDispatchEvent($this, $request);
                $this->dispatcher->dispatch($event);
                if ($event->hasResponse()) {
                    return $event->getResponse();
                }
                Events::fire(
                    "terablaze.router.dispatch.after",
                    array($url, $controller, $action, $parameters, $method)
                );

                return $this->pass($request, $controller, $action, $parameters, $requestMethod);
            }
        }

        $parts = explode("/", trim($url, "/"));

        if (!empty($url) && $url != '/') {
            if (sizeof($parts) > 0) {
                $controller = $parts[0];

                if (sizeof($parts) >= 2) {
                    $action = $parts[1];
                    $parameters = array_slice($parts, 2);
                }
            }
        }
        $event = new PostDispatchEvent($this, $request);
        $this->dispatcher->dispatch($event);
        if ($event->hasResponse()) {
            return $event->getResponse();
        }
        Events::fire("terablaze.router.dispatch.after", array($url, $controller, $action, $parameters));
        return $this->pass($request, $controller, $action, $parameters, $requestMethod);
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
                'arguments' => [$this->getRoutes()]
            ]);
        }
        return $this->container->get(UrlGenerator::class);
    }
}

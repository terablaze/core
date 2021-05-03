<?php

namespace TeraBlaze\Router;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use TeraBlaze\ArrayMethods;
use TeraBlaze\Configuration\Configuration;
use TeraBlaze\Configuration\Driver\DriverInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Controller\ControllerInterface;
use TeraBlaze\Core\Kernel\Kernel;
use TeraBlaze\Events\Events;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\Inspector;
use TeraBlaze\Router\Exception as Exception;
use TeraBlaze\Router\Exception\RequestMethod;
use TeraBlaze\Router\Generator\UrlGenerator;
use TeraBlaze\Router\Generator\UrlGeneratorInterface;
use TeraBlaze\Router\Route\{Route, Simple};

/**
 * Class Router
 * @package TeraBlaze\Router
 *
 * handles url routing
 */
class Router implements MiddlewareInterface
{
    public const SERVICE_ALIAS = "router";

    public const NAMED_ROUTE_MATCH = "{(\w[\w:.,\-'\"{}^$+*?\#\[\]()\\\\\ ]+)}";
    public const PATTERN_KEYS =
    ["#(:any)#", "#(:alpha)#", "#(:alphabet)#", "#(:num)#", "#(:numeric)#", "#(:mention)#"];
    public const PATTERN_KEYS_REPLACEMENTS =
    ["([^/]+)", "([a-zA-Z]+)", "([a-zA-Z]+)", "([\d]+)", "([\d]+)", "(@[a-zA-Z0-9-_]+)"];

    /** @var Container $container */
    protected $container;

    /** @var Kernel $kernel */
    protected $kernel;
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

    public function __construct()
    {
        $this->container = Container::getContainer();
        if ($this->container->has('app.kernel')) {
            $this->kernel = $this->container->get('app.kernel');
        }
    }

    public function addRoutes($routes, array $config = [], int $nestLevel = 0)
    {
        foreach ($routes as $name => $route) {
            if (
                !isset($route['pattern']) &&
                (isset($route['@group']) || in_array('@group', $route))
            ) {
                $groupConfig['prefix'] = ($config['prefix'] ?? '') . ($route['@prefix'] ?? '');
                $groupConfig['name_prefix'] = ($config['name_prefix'] ?? '') . ($route['@name_prefix'] ?? '');
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
            $this->addRoute($name, new Simple($route));
        }
        if ($nestLevel > 0) {
            $nestLevel--;
        }
        return;
    }

    /**
     * @param string $name
     * @param Route $route
     * @return self
     */
    public function addRoute(string $name, Route $route)
    {
        $this->routes[$name] = $route;
        return $this;
    }

    /**
     * @param $route
     * @return $this
     */
    public function removeRoute($route)
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
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $controller
     * @param string $action
     * @param array $parameters
     * @param string $method
     * @return ResponseInterface
     * @throws Exception\Action
     * @throws Exception\Controller
     * @throws ReflectionException
     */
    protected function pass(
        ServerRequestInterface $request,
        string $controller,
        string $action,
        array $parameters = array(),
        string $method = ''
    ): ResponseInterface {
        $className = ucfirst($controller);

        $this->controller = $controller;
        $this->action = $action;
        $this->method = $method;
        Events::fire("terablaze.router.controller.before", array($controller, $parameters));

        if (!class_exists($className)) {
            $nameArray = explode(":", $className);
            $className = implode("\\Controller\\", $nameArray);
        }

        if (!class_exists($className)) {
            throw new Exception\Controller("Controller '{$className}' not found");
        }

        $this->container->registerService($className, ['class' => $className]);
        /** @var ControllerInterface $controllerInstance */
        $controllerInstance = $this->container->get($className);
        $controllerInstance->setContainer($this->container);

        Events::fire("terablaze.router.controller.after", array($controller, $parameters));

        if (!method_exists($controllerInstance, $action)) {
            throw new Exception\Action("Action '{$action}' not found");
        }

        $inspector = new Inspector($controllerInstance);
        $methodMeta = $inspector->getMethodMeta($action);

        if (!empty($methodMeta["@protected"]) || !empty($methodMeta["@private"])) {
            throw new Exception\Action("Action '{$action}' not publicly accessible");
        }

        $hooks = function ($meta, $type) use ($inspector, $controllerInstance) {
            if (isset($meta[$type])) {
                $run = array();

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

        Events::fire("terablaze.router.beforehooks.before", array($action, $parameters));

        $result = $hooks($methodMeta, "@before");

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        Events::fire("terablaze.router.beforehooks.after", array($action, $parameters));

        Events::fire("terablaze.router.action.before", array($action, $parameters));


        $reflectionMethod = new \ReflectionMethod($controllerInstance, $action);
        $reflectionParameters = $reflectionMethod->getParameters();
        if (!empty($reflectionParameters) && is_object($firstArgument = $reflectionParameters[0])) {
            $reflectionClass = $firstArgument->getClass();
            $reflectionClassName = is_null($reflectionClass) ? "" : $reflectionClass->getName();
            if ($reflectionClassName === Request::class) {
                array_unshift($parameters, $request);
            }
        }
        $response = call_user_func_array([
            $controllerInstance,
            $action
        ], $parameters);

        Events::fire("terablaze.router.action.after", array($action, $parameters));
        Events::fire("terablaze.router.afterhooks.before", array($action, $parameters));

        $hooks($methodMeta, "@after");

        Events::fire("terablaze.router.afterhooks.after", array($action, $parameters));

        return $response;
    }

    /**
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception\Action
     * @throws Exception\Controller
     * @throws Exception\RequestMethod
     * @throws ReflectionException
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        /** @var Request $request */
        $url = $request->getPathInfo();
        $parameters = array();
        $controller = '';
        $action = '';

        $requestMethod = $request->getMethod();

        Events::fire("terablaze.router.dispatch.before", array($url));

        foreach ($this->routes as $route) {
            $matches = $route->matches($url);
            if ($matches) {
                $controller = $route->controller;
                $action = $route->action;
                $parameters = $route->parameters;
                $method = is_array($route->method) ? $route->method : [$route->method];
                /** @var Request $request */
                $request = $request->setExpectsJson($route->getExpectsJson());
                $this->kernel->setCurrentRequest($request);

                Events::fire("terablaze.router.dispatch.after", array($url, $controller, $action, $parameters, $method));

                $method = array_map(function ($methodItem) {
                    return strtolower($methodItem);
                }, $method);

                $method = ArrayMethods::clean($method);

                if (!in_array(strtolower($requestMethod), $method) && !empty($method)) {
                    throw new RequestMethod(
                        $method,
                        "Request method {$request->getMethod()} not implemented for this endpoint"
                    );
                }

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

        Events::fire("terablaze.router.dispatch.after", array($url, $controller, $action, $parameters));
        return $this->pass($request, $controller, $action, $parameters, $requestMethod);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var DriverInterface $configuration */
        $configuration = $this->container->get('configuration');

        $routes = $configuration->parseArray("routes");

        // add defined routes
        if (!empty($routes) && is_array($routes)) {
            $this->addRoutes($routes);
        }

        $response = $this->dispatch($request);

        return $response;
    }

    public function generate(string $name, array $parameters = [], int $referenceType = UrlGenerator::ABSOLUTE_PATH): string
    {
        return $this->getGenerator()->generate($name, $parameters, $referenceType);
    }

    /**
     * @return UrlGeneratorInterface
     * @throws ReflectionException
     */
    public function getGenerator(): UrlGeneratorInterface
    {
        if (!$this->container->has(UrlGenerator::class)) {
            $this->container->registerService(UrlGenerator::class, [
                'class' => UrlGenerator::class,
                'arguments' => [$this->getRoutes()]
            ]);
        }
        return $this->container->get(UrlGenerator::class);
    }
}

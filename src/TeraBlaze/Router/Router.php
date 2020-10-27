<?php

namespace TeraBlaze\Router;

use Nyholm\Psr7\Response;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Controller\ControllerInterface;
use TeraBlaze\Core\Kernel\Kernel;
use TeraBlaze\Events\Events;
use TeraBlaze\Inspector;
use TeraBlaze\Router\Exception as Exception;
use TeraBlaze\Router\Route\Route;

/**
 * Class Router
 * @package TeraBlaze\Router
 *
 * handles url routing
 */
class Router implements MiddlewareInterface
{
    public const SERVICE_ALIAS = "routing";

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
     * @param $method
     * @return Exception\Implementation
     */
    public function _getExceptionForImplementation($method)
    {
        return new Exception\Implementation("{$method} method not implemented");
    }

    /**
     * @param $name
     * @param $route
     * @return $this
     */
    public function addRoute($name, $route)
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
     * @param $controller
     * @param $action
     * @param array $parameters
     * @param string $method
     * @throws Exception\Action
     * @throws Exception\Controller
     * @throws \ReflectionException
     */
    protected function pass($controller, $action, $parameters = array(), $method = ''): ResponseInterface
    {
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
            throw new Exception\Controller("Controller {$className} not found");
        }

        try {
            $this->container->registerService($className, ['class' => $className]);
            /** @var ControllerInterface $controllerInstance */
            $controllerInstance = $this->container->get($className);
            $controllerInstance->setContainer($this->container);
        } catch (Exception\Controller $e) {
            throw new Exception\Controller("An error occured while loading controller {$className}");
        }

        Events::fire("terablaze.router.controller.after", array($controller, $parameters));

        if (!method_exists($controllerInstance, $action)) {
            throw new Exception\Action("Action {$action} not found");
        }

        $inspector = new Inspector($controllerInstance);
        $methodMeta = $inspector->getMethodMeta($action);

        if (!empty($methodMeta["@protected"]) || !empty($methodMeta["@private"])) {
            throw new Exception\Action("Action {$action} not publicly accessible");
        }

        $hooks = function ($meta, $type) use ($inspector, $controllerInstance) {
            if (isset($meta[$type])) {
                $run = array();

                foreach ($meta[$type] as $method) {
                    $hookMeta = $inspector->getMethodMeta($method);

                    if (in_array($method, $run) && !empty($hookMeta["@once"])) {
                        continue;
                    }

                    $controllerInstance->$method();
                    $run[] = $method;
                }
            }
        };

        Events::fire("terablaze.router.beforehooks.before", array($action, $parameters));

        $hooks($methodMeta, "@before");

        Events::fire("terablaze.router.beforehooks.after", array($action, $parameters));

        Events::fire("terablaze.router.action.before", array($action, $parameters));

        $response = call_user_func_array([
            $controllerInstance,
            $action
        ], is_array($parameters) ? $parameters : array());

        Events::fire("terablaze.router.action.after", array($action, $parameters));
        Events::fire("terablaze.router.afterhooks.before", array($action, $parameters));

        $hooks($methodMeta, "@after");

        Events::fire("terablaze.router.afterhooks.after", array($action, $parameters));

        return $response;
    }

    /**
     *
     * @param ServerRequestInterface|null $request
     * @return ResponseInterface
     * @throws Exception\Action
     * @throws Exception\Controller
     * @throws Exception\RequestMethod
     * @throws \ReflectionException
     */
    public function dispatch(ServerRequestInterface $request = null): ResponseInterface
    {
        $url = $request->getServerParams()['PATH_INFO'] ?? "/";
        $parameters = array();
        $controller = '';
        $action = '';

        Events::fire("terablaze.router.dispatch.before", array($url));

        foreach ($this->routes as $route) {
            $matches = $route->matches($url);
            if ($matches) {
                $controller = $route->controller;
                $action = $route->action;
                $parameters = $route->parameters;
                $method = $route->method;

                $request_method = strtolower($_SERVER['REQUEST_METHOD']);

                Events::fire("terablaze.router.dispatch.after", array($url, $controller, $action, $parameters, $method));

                if (
                    (is_array($method) && in_array($request_method, $method)) || 
                    $request_method === $method || empty($method)
                ) {
                    return $this->pass($controller, $action, $parameters, $request_method);
                } else {
                    http_response_code(405);
                    throw new Exception\RequestMethod("Request method {$_SERVER['REQUEST_METHOD']} not implemented for this endpoint");
                }
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
        return $this->pass($controller, $action, $parameters);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->container = Container::getContainer();

        /** @var Kernel $kernel */
        $kernel = $this->container->get('app.kernel');

        $routes = require $kernel->getProjectDir() . '/config/routes.php';

        // add defined routes
        if (!empty($routes) && is_array($routes)) {
            foreach ($routes as $name => $route) {
                $this->addRoute($name, new \TeraBlaze\Router\Route\Simple($route));
            }
        }

        $response = $this->dispatch($request);

        return $response;
    }
}

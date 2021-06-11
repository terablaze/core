<?php

namespace TeraBlaze\Profiler\Debugbar\DataCollectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use TeraBlaze\Container\Container;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\Routing\Route;
use TeraBlaze\Routing\Router;

class RouteCollector extends DataCollector implements Renderable
{
    /** @var Router $router */
    protected $router;

    /** @var Container $container */
    protected $container;

    public function __construct(Router $router, Container $container = null)
    {
        $this->router = $router;
        $this->container = $container ?: Container::getContainer();
    }

    /**
     * {@inheritDoc}
     */
    public function collect()
    {
        $route = $this->router->getCurrentRoute();
        return $this->getRouteInformation($route);
    }

    /**
     * @param Route $route
     * @return array|string[]
     * @throws \ReflectionException
     */
    protected function getRouteInformation($route)
    {
        if (!is_a($route, Route::class)) {
            return [];
        }
        $routeMethods = $route->getMethod();
        $uri = strtoupper(reset($routeMethods)) . ' /' . $route->getPath();
        $controller = $route->getController();
        $action = $route->getAction();

        $result = [
            'name' => $route->getName(),
            'uri' => $uri ?: '-',
            'params' => $route->getParameters(),
        ];

//        $result = array_merge($result, [$action]);


        if (isset($controller) && is_string($controller)) {
            if (class_exists($controller) && method_exists($controller, $action)) {
                $reflector = new \ReflectionMethod($controller, $action);
                $result['controller'] = $controller;
                $result['action'] = $action;
            }
        }
        if (isset($reflector)) {
            $filename = ltrim(
                str_replace(
                    $this->container->get('kernel')->getProjectDir(),
                    '',
                    $reflector->getFileName()),
                '/'
            );
            $result['file'] = $filename . ':' . $reflector->getStartLine() . '-' . $reflector->getEndLine();
        }

        if ($middleware = $this->getMiddleware($route)) {
            $result['middleware'] = $middleware;
        }


        return $result;
    }

    /**
     * @param Route $route
     * @return string
     */
    protected function getMiddleware(Route $route)
    {
        return implode(', ', $route->getMiddlewares());
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'route';
    }

    /**
     * {@inheritDoc}
     */
    public function getWidgets()
    {
        $widgets = [
            "route" => [
                "icon" => "share",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "route",
                "default" => "{}"
            ]
        ];
        $widgets['currentroute'] = [
            "icon" => "share",
            "tooltip" => "Route",
            "map" => "route.uri",
            "default" => ""
        ];
        return $widgets;
    }

    /**
     * Display the route information on the console.
     *
     * @param array $routes
     * @return void
     */
    protected function displayRoutes(array $routes)
    {
        $this->table->setHeaders($this->headers)->setRows($routes);

        $this->table->render($this->getOutput());
    }
}

<?php
/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 1/30/2017
 * Time: 6:06 PM
 */

namespace TeraBlaze\Router\Route;

use TeraBlaze\Base as Base;
use TeraBlaze\Router\Exception as Exception;

/**
 * Class Route
 * @package TeraBlaze\Router
 */
abstract class Route
{
	/**
	 * @readwrite
	 */
	public $path;

	/**
	 * @readwrite
	 */
	public $action;

    /**
     * @readwrite
     */
    public $method;

    /**
     * @readwrite
     */
    public $controller;

	/**
	 * @readwrite
	 */
	public $parameters = array();

	public function __construct($route)
    {
        $this->path = $route['pattern'] ?? null;
        $this->controller = $route['controller'] ?? null;
        $this->action = $route['action'] ?? null;
        $this->method = $route['method'] ?? null;
        $this->parameters = $route['parameters'] ?? [];
    }

    abstract function matches($url): bool;

    /**
	 * @param $method
	 * @return Exception\Implementation
	 */
	public function _getExceptionForImplementation($method)
	{
		return new Exception\Implementation("{$method} method not implemented");
	}
}

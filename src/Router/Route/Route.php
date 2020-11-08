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
	/** @var string $path */
	public $path;

	/** @var string $action */
	public $action;

	/** @var string|array|null $method */
    public $method;

	/** @var string $controller */
    public $controller;

	/** @var array $parameters */
	public $parameters = [];

    public function __construct($route)
    {
        $this->path = $route['pattern'] ?? null;
        $this->controller = $route['controller'] ?? null;
        $this->action = $route['action'] ?? null;
        $this->method = $route['method'] ?? null;
    }

    abstract function matches($url): bool;

    /**
     * @return string
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @return array|string|null
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getController(): ?string
    {
        return $this->controller;
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
	 * @param $method
	 * @return Exception\Implementation
	 */
	public function _getExceptionForImplementation($method)
	{
		return new Exception\Implementation("{$method} method not implemented");
	}
}

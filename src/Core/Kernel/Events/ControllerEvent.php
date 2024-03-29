<?php

namespace Terablaze\Core\Kernel\Events;

use Terablaze\Core\Kernel\HttpKernelInterface;
use Terablaze\HttpBase\Request;

/**
 * Allows filtering of a controller callable.
 *
 * You can call getController() to retrieve the current controller. With
 * setController() you can set a new controller that is used in the processing
 * of the request.
 *
 * Controllers should be callables.
 *
 */
final class ControllerEvent extends KernelEvent
{
    private $controller;

    public function __construct(HttpKernelInterface $kernel, callable $controller, Request $request)
    {
        parent::__construct($kernel, $request);

        $this->setController($controller);
    }

    public function getController(): callable
    {
        return $this->controller;
    }

    public function setController(callable $controller): void
    {
        $this->controller = $controller;
    }
}

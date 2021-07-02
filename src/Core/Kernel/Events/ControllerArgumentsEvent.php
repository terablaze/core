<?php

namespace TeraBlaze\Core\Kernel\Events;

use TeraBlaze\Core\Kernel\HttpKernelInterface;
use TeraBlaze\HttpBase\Request;

/**
 * Allows filtering of controller arguments.
 *
 * You can call getController() to retrieve the controller and getArguments
 * to retrieve the current arguments. With setArguments() you can replace
 * arguments that are used to call the controller.
 *
 * Arguments set in the event must be compatible with the signature of the
 * controller.
 */
final class ControllerArgumentsEvent extends KernelEvent
{
    private $controller;
    private $arguments;

    /**
     * ControllerArgumentsEvent constructor.
     * @param HttpKernelInterface $kernel
     * @param callable $controller
     * @param array $arguments
     * @param Request $request
     */
    public function __construct(HttpKernelInterface $kernel, callable $controller, array $arguments, Request $request)
    {
        parent::__construct($kernel, $request);

        $this->controller = $controller;
        $this->arguments = $arguments;
    }

    public function getController(): callable
    {
        return $this->controller;
    }


    public function setController(callable $controller)
    {
        $this->controller = $controller;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
    }
}

<?php

namespace Terablaze\Routing\Events;

use Psr\Http\Message\ServerRequestInterface;
use Terablaze\Routing\Router;

class PostControllerEvent extends RouterEvent
{
    private object $controllerInstance;

    public function __construct(Router $router, ServerRequestInterface $request, object $controllerInstance)
    {
        parent::__construct($router, $request);
        $this->controllerInstance = $controllerInstance;
    }

    /**
     * @return object
     */
    public function getControllerInstance(): object
    {
        return $this->controllerInstance;
    }

    /**
     * @param object $controllerInstance
     * @return $this
     */
    public function setControllerInstance(object $controllerInstance): PostControllerEvent
    {
        $this->controllerInstance = $controllerInstance;
        return $this;
    }
}

<?php

namespace Terablaze\Routing\Events;

use Psr\Http\Message\ServerRequestInterface;
use Terablaze\Routing\Router;

class PreControllerEvent extends RouterEvent
{
    private string $controller;

    public function __construct(Router $router, ServerRequestInterface $request, string $controller)
    {
        parent::__construct($router, $request);
        $this->controller = $controller;
    }

    /**
     * @return string
     */
    public function getController(): string
    {
        return $this->controller;
    }

    /**
     * @param string $controller
     * @return PreControllerEvent
     */
    public function setController(string $controller): PreControllerEvent
    {
        $this->controller = $controller;
        return $this;
    }
}

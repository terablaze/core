<?php

namespace TeraBlaze\Routing\Events;

class PostBeforeHookEvent
{
    private string $controllerAction;
    private array $parameters;

    public function __construct(string $controllerAction, array $parameters)
    {
        $this->controllerAction = $controllerAction;
        $this->parameters = $parameters;
    }

    /**
     * @return string
     */
    public function getControllerAction(): string
    {
        return $this->controllerAction;
    }

    /**
     * @param string $controllerAction
     * @return PostBeforeHookEvent
     */
    public function setControllerAction(string $controllerAction): PostBeforeHookEvent
    {
        $this->controllerAction = $controllerAction;
        return $this;
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param array $parameters
     * @return PostBeforeHookEvent
     */
    public function setParameters(array $parameters): PostBeforeHookEvent
    {
        $this->parameters = $parameters;
        return $this;
    }
}

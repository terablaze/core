<?php

namespace TeraBlaze\Routing\Event;

class PreBeforeHookEvent
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
     * @return PreBeforeHookEvent
     */
    public function setControllerAction(string $controllerAction): PreBeforeHookEvent
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
     * @return PreBeforeHookEvent
     */
    public function setParameters(array $parameters): PreBeforeHookEvent
    {
        $this->parameters = $parameters;
        return $this;
    }
}

<?php

namespace TeraBlaze\View;

use TeraBlaze\View\Engine\EngineInterface ;

class View
{
    public string $path;

    /** @var array<string, mixed> $data */
    public array $data;

    protected EngineInterface $engine;

    public function __construct(
        EngineInterface $engine,
        string $path,
        array $data
    ) {
        $this->data = $data;
        $this->path = $path;
        $this->engine = $engine;
    }

    /**
     * @param string $path
     * @return View
     */
    public function setPath(string $path): View
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param array $data
     * @return View
     */
    public function setData(array $data): View
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param EngineInterface $engine
     * @return View
     */
    public function setEngine(EngineInterface $engine): View
    {
        $this->engine = $engine;
        return $this;
    }

    /**
     * @return EngineInterface
     */
    public function getEngine(): EngineInterface
    {
        return $this->engine;
    }

    public function render(): string
    {
        return $this->engine->render($this);
    }

    public function __toString()
    {
        return $this->render();
    }
}

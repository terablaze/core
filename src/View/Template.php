<?php

namespace TeraBlaze\View;

use TeraBlaze\View\Engine\EngineInterface ;

class Template
{
    public string $name;
    public string $path;
    public string $namespace;

    /** @var array<string, mixed> $data */
    public array $data;

    protected EngineInterface $engine;

    /**
     * Template constructor.
     * @param EngineInterface $engine
     * @param string $path
     * @param array<string, mixed> $data
     * @param string $namespace
     */
    public function __construct(
        EngineInterface $engine,
        string $name,
        string $path,
        array $data,
        string $namespace = ''
    ) {
        $this->name = $name;
        $this->data = $data;
        $this->path = $path;
        $this->engine = $engine;
        $this->namespace = $namespace;
    }

    /**
     * @param string $path
     * @return Template
     */
    public function setPath(string $path): Template
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
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param array $data
     * @return Template
     */
    public function setData(array $data): Template
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
     * @return Template
     */
    public function setEngine(EngineInterface $engine): Template
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

    /**
     * @param string $namespace
     * @return Template
     */
    public function setNamespace(string $namespace): Template
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
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

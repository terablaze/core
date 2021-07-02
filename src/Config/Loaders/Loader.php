<?php

namespace TeraBlaze\Config\Loaders;

abstract class Loader implements LoadableInterface
{
    /** @var string Path to a configuration file or directory */
    protected string $context;

    /**
     * Create a new Loader object.
     *
     * @param string $context Path to configuration file or directory
     */
    public function __construct(string $context)
    {
        $this->context = $context;
    }

    /**
     * Retrieve the context as an array of configuration options.
     *
     * @return array<string, mixed> Array of configuration options
     */
    abstract public function getArray(): array;
}

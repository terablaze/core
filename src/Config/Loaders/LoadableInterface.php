<?php

namespace Terablaze\Config\Loaders;

interface LoadableInterface
{
    /**
     * Retrieve the contents of one or more configuration files and convert them
     * to an array of configuration options.
     *
     * @return array<string, mixed> Array of configuration options
     */
    public function getArray(): array;
}

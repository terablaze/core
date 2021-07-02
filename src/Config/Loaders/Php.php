<?php

namespace TeraBlaze\Config\Loaders;


use TeraBlaze\Config\Exception\InvalidFileException;

class Php extends Loader
{
    /**
     * Retrieve the contents of a .php configuration file and convert it to an
     * array of configuration options.
     *
     * @throws InvalidFileException
     *
     * @return array<string, mixed> Array of configuration options
     */
    public function getArray(): array
    {
        $contents = include $this->context;

        if (!is_array($contents)) {
            throw new InvalidFileException($this->context . ' does not return a valid array');
        }

        return $contents;
    }
}

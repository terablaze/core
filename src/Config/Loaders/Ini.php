<?php

namespace TeraBlaze\Config\Loaders;

use TeraBlaze\Config\Exception\InvalidFileException;

class Ini extends Loader
{
    /**
     * Retrieve the contents of a .ini file and convert it to an array of
     * configuration options.
     *
     * @throws InvalidFileException
     *
     * @return array<string, mixed> Array of configuration options
     */
    public function getArray(): array
    {
        $parsed = @parse_ini_file($this->context, true);

        if (! $parsed) {
            throw new InvalidFileException('Unable to parse invalid INI file at ' . $this->context);
        }

        return $parsed;
    }
}

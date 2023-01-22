<?php

namespace Terablaze\Config\Loaders;

use Terablaze\Config\Exception\InvalidFileException;

class Json extends Loader
{
    /**
     * Retrieve the contents of a .json file and convert it to an array of
     * configuration options.
     *
     * @throws InvalidFileException
     *
     * @return array<string, mixed> Array of configuration options
     */
    public function getArray(): array
    {
        $contents = file_get_contents($this->context);

        $parsed = json_decode($contents, true);

        if (is_null($parsed)) {
            throw new InvalidFileException('Unable to parse invalid JSON file at ' . $this->context);
        }

        return $parsed;
    }
}

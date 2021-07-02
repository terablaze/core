<?php

namespace TeraBlaze\Config\Loaders;

use DirectoryIterator;
use TeraBlaze\Config\Exception\InvalidFileException;

class Directory extends Loader
{
    /**
     * Retrieve the contents of one or more configuration files in a directory
     * and convert them to an array of configuration options. Any invalid files
     * will be silently ignored.
     *
     * @return array<string, mixed> Array of configuration options
     */
    public function getArray(): array
    {
        $contents = [];

        foreach (new DirectoryIterator($this->context) as $file) {
            if ($file->isDot()) {
                continue;
            }

            $className = $file->isDir() ? 'Directory' : ucfirst(strtolower($file->getExtension()));
            $classPath = 'TeraBlaze\\Config\\Loaders\\' . $className;

            $loader = new $classPath($file->getPathname());

            try {
                $contents = array_merge($contents, $loader->getArray());
            } catch (InvalidFileException $e) {
                // Ignore it and continue
            }
        }

        return $contents;
    }
}

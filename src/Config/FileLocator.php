<?php

namespace Terablaze\Config;

/**
 * Class FileLocator
 *
 * Locates files in one or more directory paths.
 *
 * @package Terablaze\Config
 */
class FileLocator implements FileLocatorInterface
{
    /**
     * The list of directory paths.
     *
     * @var string[]
     */
    private array $directories = [];

    /**
     * Sets the list of directory paths, if provided.
     *
     * @param string[] $directories The directory paths.
     *
//     * @throws ArgumentException If a directory path does not exist.
     */
    public function __construct(array $directories)
    {
//        foreach ($directories as $path) {
//            if (false === is_dir($path)) {
//                throw new ArgumentException(sprintf(
//                    'The path "%s" is not a valid directory path.',
//                    $path
//                ));
//            }
//        }

        $this->directories = $directories;
    }

    /**
     * {@inheritDoc}
     */
    public function locate(string $file, bool $first = true): array
    {
        $paths = [];
        $fileArray = [];

        foreach (self::FILE_EXTENSION_ORDER as $fileExtension) {
            $fileArray[] = "$file.$fileExtension";
        }

        array_unshift($fileArray, $file);

        foreach ($this->directories as $directory) {
            foreach ($fileArray as $file) {
                $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . ltrim($file, '/\\');

                if (file_exists($path)) {
                    if ($first) {
                        return [$path];
                    }

                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }
}

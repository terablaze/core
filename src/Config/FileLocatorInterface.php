<?php

namespace TeraBlaze\Config;

interface FileLocatorInterface
{
    public const FILE_EXTENSION_ORDER = ['php', 'yaml', 'json', 'xml', 'toml'];

    /**
     * Returns the file path(s) for the file name.
     *
     * @param string $file The file name.
     * @param bool $first Only return the first result?
     *
     * @return string[] The absolute file paths.
     */
    public function locate(string $file, bool $first = true): array;
}

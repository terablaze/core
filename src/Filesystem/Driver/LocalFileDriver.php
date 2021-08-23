<?php

namespace TeraBlaze\Filesystem\Driver;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class LocalFileDriver extends FileDriver implements FileDriverInterface
{
    public function connect(): void
    {
        $root = baseDir($this->config['root'] ?? '');

        $links = ($config['links'] ?? null) === 'skip'
            ? LocalFilesystemAdapter::SKIP_LINKS
            : LocalFilesystemAdapter::DISALLOW_LINKS;

        $adapter = new LocalFilesystemAdapter($root, null, LOCK_EX, $links);
        $this->filesystem = new Filesystem($adapter);
    }
}

<?php

namespace TeraBlaze\Filesystem\Driver;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;

class LocalFileDriver extends FileDriver implements FileDriverInterface
{
    public function connect(): void
    {
        $root = $this->config['root'] ?? '';
        if (!is_dir($root)) {
            $root = baseDir($root);
        }

        $links = ($this->config['links'] ?? null) === 'skip'
            ? LocalFilesystemAdapter::SKIP_LINKS
            : LocalFilesystemAdapter::DISALLOW_LINKS;

        $visibility = PortableVisibilityConverter::fromArray([
            'file' => [
                'public' => $this->config['file']['public'] ?? 0640,
                'private' => $this->config['file']['private'] ?? 0604,
            ],
            'dir' => [
                'public' => $this->config['directory']['public'] ?? $this->config['dir']['public'] ?? 0740,
                'private' => $this->config['directory']['private'] ?? $this->config['dir']['private'] ?? 7604,
            ],
        ], $this->config['visibility'] ?? Visibility::PRIVATE);

        $adapter = new LocalFilesystemAdapter($root, $visibility, $this->config['lock'] ?? LOCK_EX, $links);
        $this->filesystem = new Filesystem($adapter);
    }
}

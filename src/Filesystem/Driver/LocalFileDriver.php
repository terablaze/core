<?php

namespace Terablaze\Filesystem\Driver;

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
                'public' => $this->config['permission']['file']['public'] ?? 0640,
                'private' => $this->config['permission']['file']['private'] ?? 0604,
            ],
            'dir' => [
                'public' => $this->config['permission']['directory']['public'] ??
                    $this->config['permission']['dir']['public'] ?? 0740,
                'private' => $this->config['permission']['directory']['private'] ??
                    $this->config['permission']['dir']['private'] ?? 7604,
            ],
        ], $this->config['visibility'] ?? Visibility::PRIVATE);

        $adapter = new LocalFilesystemAdapter($root, $visibility, $this->config['lock'] ?? LOCK_EX, $links);
        $this->filesystem = new Filesystem($adapter);
    }
}

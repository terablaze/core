<?php

namespace TeraBlaze\Filesystem\Driver;

use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use TeraBlaze\Filesystem\Exception\UploadedFileException;

interface FileDriverInterface
{
    /**
     * Creates a filesystem instance using an adapter specified in config
     */
    public function connect(): void;

    public function exists(string $path): bool;

    public function read(string $path, bool $asResource = false);

    public function write(string $path, $value, array $config = []): void;

    public function delete(string $path): void;

    public function deleteDirectory(string $path): void;

    public function createDirectory(string $path, array $config = []): void;

    public function list(string $path, bool $recursive = false);

    public function move(string $source, string $destination, array $config = []): void;

    public function copy(string $source, string $destination, array $config = []): void;

    public function lastModified(string $path): int;

    public function fileSize(string $path): int;

    public function mimeType(string $path): string;

    public function setVisibility(string $path, string $visibility): void;

    public function visibility(string $path): string;

    public function getVisibility(string $path): string;

    public function getFlysystem(): Filesystem;
}

<?php

namespace TeraBlaze\Filesystem\Driver;

use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

interface FileDriverInterface
{
    /**
     * Creates a filesystem instance using an adapter specified in config
     */
    public function connect(): void;

    /**
     * List files and directories in the specified path
     *
     * @param string $path
     * @param bool $recursive
     * @return DirectoryListing<FileAttributes|DirectoryAttributes>|iterable<FileAttributes|DirectoryAttributes>
     */
    public function list(string $path, bool $recursive = false);

    /**
     * Checks whether a file exists
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Read the content of a file in the specified path
     *
     * @param string $path
     * @param bool $asResource
     * @return string|resource
     * @throws FilesystemException
     */
    public function read(string $path, bool $asResource = false);

    /**
     * Writes a file to the specified path
     *
     * @param string $path
     * @param string|resource|StreamInterface|UploadedFileInterface $value
     * @param string[] $config
     * @return $this
     * @throws FilesystemException
     */
    public function write(string $path, $value, array $config = []): FileDriverInterface;

    /**
     * Deletes a file
     *
     * @param string $path
     * @return FileDriverInterface
     */
    public function delete(string $path): FileDriverInterface;

    /**
     * Deletes a directory
     *
     * @param string $path
     * @return FileDriverInterface
     */
    public function deleteDirectory(string $path): FileDriverInterface;
}

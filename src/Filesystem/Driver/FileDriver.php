<?php

namespace TeraBlaze\Filesystem\Driver;

use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use TeraBlaze\Filesystem\Exception\UploadedFileException;

abstract class FileDriver implements FileDriverInterface
{
    protected Filesystem $filesystem;

    /**
     * @var array<string, mixed> $config
     */
    protected array $config = [];
    /**
     * @var mixed|string
     */
    protected string $root;

    /**
     * Driver constructor.
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->root = $this->config['root'] ?? "";
        $this->connect();
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->fileExists($path);
    }

    public function read(string $path, bool $asResource = false)
    {
        if ($asResource) {
            return $this->filesystem->readStream($path);
        }
        return $this->filesystem->read($path);
    }

    public function write(string $path, $value, array $config = []): void
    {
        $config = array_merge($this->config, $config);

        if (is_string($value)) {
            $this->filesystem->write($path, $value, $config);
        }
        if ($value instanceof UploadedFileInterface) {
            if ($value->getError() !== UPLOAD_ERR_OK) {
                throw new UploadedFileException('There is an error with the uploaded file', $value->getError());
            }
            $value = $value->getStream();
        }
        if ($value instanceof StreamInterface) {
            $value = $value->detach();
        }
        if (is_resource($value)) {
            $this->filesystem->writeStream($path, $value, $config);
        }
    }

    public function delete(string $path): void
    {
        $this->filesystem->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->filesystem->deleteDirectory($path);
    }

    public function createDirectory(string $path, array $config = []): void
    {
        $this->filesystem->createDirectory($path, $config);
    }

    /**
     * List files and directories in the specified path
     *
     * @param string $path
     * @param bool $recursive
     * @return DirectoryListing<FileAttributes|DirectoryAttributes>|iterable<FileAttributes|DirectoryAttributes>
     */
    public function list(string $path, bool $recursive = false)
    {
        return $this->filesystem->listContents($path, $recursive);
    }

    public function move(string $source, string $destination, array $config = []): void
    {
        $this->filesystem->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, array $config = []): void
    {
        $this->filesystem->copy($source, $destination, $config);
    }

    public function lastModified(string $path): int
    {
        return $this->filesystem->lastModified($path);
    }

    public function fileSize(string $path): int
    {
        return $this->filesystem->fileSize($path);
    }

    public function mimeType(string $path): string
    {
        return $this->filesystem->mimeType($path);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->filesystem->setVisibility($path, $visibility);
    }

    public function visibility(string $path): string
    {
        return $this->filesystem->visibility($path);
    }

    public function getVisibility(string $path): string
    {
        return $this->visibility($path);
    }

    public function getFlysystem(): Filesystem
    {
        return $this->filesystem;
    }
}

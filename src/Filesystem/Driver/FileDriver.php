<?php

namespace TeraBlaze\Filesystem\Driver;

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
     * Driver constructor.
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        $this->connect();
    }

    /**
     * @inheritDoc
     */
    public function list(string $path, bool $recursive = false)
    {
        return $this->filesystem->listContents($path, $recursive);
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

    public function write(string $path, $value, array $config = []): FileDriverInterface
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
        return $this;
    }

    public function delete(string $path): FileDriverInterface
    {
        $this->filesystem->delete($path);
        return $this;
    }

    public function deleteDirectory(string $path): FileDriverInterface
    {
        $this->filesystem->deleteDirectory($path);
        return $this;
    }
}

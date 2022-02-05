<?php

namespace TeraBlaze\Filesystem\Driver;

use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client as AsyncS3Client;
use AsyncAws\SimpleS3\SimpleS3Client;
use Aws\S3\S3Client;
use DateTimeImmutable;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use TeraBlaze\Filesystem\Exception\ConfigurationException;
use TeraBlaze\Filesystem\Exception\DriverException;
use TeraBlaze\Filesystem\Exception\ServiceException;
use TeraBlaze\Support\ArrayMethods;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\AsyncAwsS3\PortableVisibilityConverter as AsyncPortableVisibilityConverter;
use TeraBlaze\Support\StringMethods;

/**
 * Class S3Driver
 * @package TeraBlaze\Filesystem\Driver
 *
 * Only usable after installing "league/flysystem-aws-s3-v3" package
 */
class S3FileDriver extends FileDriver implements FileDriverInterface
{
    /** @var AsyncS3Client|SimpleS3Client|S3Client */
    private $client;

    public function connect(): void
    {
        if (!empty($this->config["visibility"]) && !in_array($this->config["visibility"], ["public", "private"])) {
            throw new ConfigurationException(
                "Invalid visibility value supplied, only \"public\" and \"private\" are allowed"
            );
        }

        switch ($this->config['adapter']) {
            case "async":
                $this->connectAsyncS3();
                break;
            case "simples3":
                $this->connectSimpleAsyncS3();
                break;
            case "s3":
            default:
                $this->connectS3();
        }
    }

    private function connectS3()
    {
        if (!class_exists(AwsS3V3Adapter::class)) {
            throw new DriverException(
                "To support use of s3 with flysystem, please run 'composer require league/flysystem-aws-s3-v3'"
            );
        }
        $s3Config = $this->formatS3Config($this->config);
        $visibility = new PortableVisibilityConverter($this->config["visibility"]);
        $adapter = new AwsS3V3Adapter(
            $this->client = new S3Client($s3Config),
            $s3Config['bucket'],
            $this->root,
            $visibility,
            null,
            $this->config['options'] ?? [],
            $this->config['stream_reads'] ?? false
        );
        $this->filesystem = new Filesystem($adapter);
    }

    private function connectAsyncS3()
    {
        if (!class_exists(AsyncAwsS3Adapter::class)) {
            throw new DriverException(
                "To support use of async s3 with flysystem, please run 'composer require league/flysystem-async-aws-s3'"
            );
        }
        $visibility = new AsyncPortableVisibilityConverter($this->config["visibility"]);
        $s3Config = $this->formatAsyncS3Config($this->config);
        $adapter = new AsyncAwsS3Adapter(
            $this->client = new AsyncS3Client($s3Config),
            $this->config['bucket'],
            $this->root,
            $visibility,
            null
        );
        $this->filesystem = new Filesystem($adapter);
    }

    private function connectSimpleAsyncS3()
    {
        if (!class_exists(AsyncAwsS3Adapter::class) && !class_exists(SimpleS3Client::class)) {
            throw new DriverException(
                "To support use of simple s3 client with flysystem and AsyncAws,"
                . " please run 'composer require league/flysystem-async-aws-s3 async-aws/simple-s3'"
            );
        }
        if (!class_exists(AsyncAwsS3Adapter::class)) {
            throw new DriverException(
                "To support use of async s3 with flysystem, please run 'composer require league/flysystem-async-aws-s3'"
            );
        }
        if (!class_exists(SimpleS3Client::class)) {
            throw new DriverException(
                "To support use of simple s3 client with flysystem and AsyncAws,"
                . " please run 'composer require async-aws/simple-s3'"
            );
        }
        $visibility = new AsyncPortableVisibilityConverter($this->config["visibility"]);
        $s3Config = $this->formatAsyncS3Config($this->config);
        $adapter = new AsyncAwsS3Adapter(
            $this->client = new SimpleS3Client($s3Config),
            $this->config['bucket'],
            $this->root,
            $visibility,
            null
        );
        $this->filesystem = new Filesystem($adapter);
    }

    /**
     * Format the given S3 configuration with the default options.
     *
     * @param array $config
     * @return array
     */
    protected function formatS3Config(array $config)
    {
        $config += ['version' => 'latest'];

        if (!empty($config['key']) && !empty($config['secret'])) {
            $config['credentials'] = ArrayMethods::only($config, ['key', 'secret', 'token']);
        }

        return $config;
    }

    /**
     * Format the given S3 configuration with the default options.
     *
     * @param array $config
     * @return array
     */
    protected function formatAsyncS3Config(array $config)
    {
        return [
            'accessKeyId' => $config['key'],
            'accessKeySecret' => $config['secret'],
            'region' => $config['region'],
            'endpoint' => $config['endpoint'],
        ];
    }

    /**
     * @return AsyncS3Client|SimpleS3Client|S3Client
     */
    public function getClient()
    {
        return $this->client;
    }

    public function temporaryGetUrl(string $fileUrl, $expiration = "+1 hour", array $options = []): string
    {
        $fileUrl = $this->applyPathPrefix(ltrim($fileUrl, '\\/'));
        $options = array_merge([
            'Bucket' => $this->config['bucket'],
            'Key' => $fileUrl,
        ], $options);
        if ($this->client instanceof S3Client) {
            return $this->createTemporaryS3GetUrl($expiration, $options);
        }
        if ($this->client instanceof AsyncS3Client) {
            return $this->createTemporaryAsyncS3GetUrl($expiration, $options);
        }
        return "";
    }

    public function temporaryUploadUrl(string $fileUrl, $expiration = "+1 hour", array $options = []): string
    {
        $fileUrl = $this->applyPathPrefix(ltrim($fileUrl, '\\/'));
        $options = array_merge([
            'Bucket' => $this->config['bucket'],
            'Key' => $fileUrl,
            'ContentType' => 'application-json',
            'Body' => '',
        ], $options);
        if ($this->client instanceof S3Client) {
            return $this->createTemporaryS3UploadUrl($expiration, $options);
        }
        if ($this->client instanceof AsyncS3Client) {
            return $this->createTemporaryAsyncS3UploadUrl($expiration, $options);
        }
        return "";
    }

    private function createTemporaryS3GetUrl($expiration, array $options)
    {
        // TODO: Convert DateTimeImmutable to acceptable string, e.g. +1 day
        $command = $this->client->getCommand('getObject', $options);
        $request = $this->client->createPresignedRequest($command, $expiration);
        return (string)$request->getUri();
    }

    private function createTemporaryAsyncS3GetUrl($expiration, array $options)
    {
        if (!$expiration instanceof DateTimeImmutable) {
            $expiration = new DateTimeImmutable($expiration);
        }
        $input = new GetObjectRequest($options);
        return $this->client->presign($input, $expiration);
    }

    private function createTemporaryS3UploadUrl($expiration, $options)
    {
        // TODO: Convert DateTimeImmutable to acceptable string, e.g. +1 day
        $command = $this->client->getCommand('putObject', $options);
        $request = $this->client->createPresignedRequest($command, $expiration);
        return (string)$request->getUri();
    }

    private function createTemporaryAsyncS3UploadUrl($expiration, $options)
    {
        if (!$expiration instanceof DateTimeImmutable) {
            $expiration = new DateTimeImmutable($expiration);
        }
        $input = new PutObjectRequest($options);
        return $this->client->presign($input, $expiration);
    }

    private function applyPathPrefix(string $path): string
    {
        if (
            !empty($this->root) &&
            StringMethods::startsWith($path, $this->root)
        ) {
            return $path;
        }
        return $this->root . DIRECTORY_SEPARATOR . $path;
    }
}

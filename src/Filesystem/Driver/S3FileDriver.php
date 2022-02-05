<?php

namespace TeraBlaze\Filesystem\Driver;

use AsyncAws\S3\S3Client as AsyncS3Client;
use AsyncAws\SimpleS3\SimpleS3Client;
use Aws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use TeraBlaze\Filesystem\Exception\ConfigurationException;
use TeraBlaze\Filesystem\Exception\DriverException;
use TeraBlaze\Support\ArrayMethods;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\AsyncAwsS3\PortableVisibilityConverter as AsyncPortableVisibilityConverter;

/**
 * Class S3Driver
 * @package TeraBlaze\Filesystem\Driver
 *
 * Only usable after installing "league/flysystem-aws-s3-v3" package
 */
class S3FileDriver extends FileDriver implements FileDriverInterface
{
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
            new S3Client($s3Config),
            $s3Config['bucket'],
            $this->config['root'] ?? "",
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
                "To support use of async s3 with flysystem, please run 'composer require league/flysystem-async-aws-v3'"
            );
        }
        $visibility = new AsyncPortableVisibilityConverter($this->config["visibility"]);
        $s3Config = $this->formatAsyncS3Config($this->config);
        $adapter = new AsyncAwsS3Adapter(
            new AsyncS3Client($s3Config),
            $this->config['bucket'],
            $this->config['root'] ?? "",
            $visibility,
            null
        );
        $this->filesystem = new Filesystem($adapter);
    }

    private function connectSimpleAsyncS3()
    {
        if (!class_exists(AsyncAwsS3Adapter::class)) {
            throw new DriverException(
                "To support use of async s3 with flysystem, please run 'composer require league/flysystem-async-aws-v3'"
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
            new SimpleS3Client($s3Config),
            $this->config['bucket'],
            $this->config['root'] ?? "",
            $visibility,
            null
        );
        $this->filesystem = new Filesystem($adapter);
    }

    /**
     * Format the given S3 configuration with the default options.
     *
     * @param  array  $config
     * @return array
     */
    protected function formatS3Config(array $config)
    {
        $config += ['version' => 'latest'];

        if (! empty($config['key']) && ! empty($config['secret'])) {
            $config['credentials'] = ArrayMethods::only($config, ['key', 'secret', 'token']);
        }

        return $config;
    }

    /**
     * Format the given S3 configuration with the default options.
     *
     * @param  array  $config
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
}

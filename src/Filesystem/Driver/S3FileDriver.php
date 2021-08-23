<?php

namespace TeraBlaze\Filesystem\Driver;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use TeraBlaze\ArrayMethods;

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
        $s3Config = $this->formatS3Config($this->config);

        $root = $s3Config['root'] ?? null;

        $options = $this->config['options'] ?? [];

        $streamReads = $this->config['stream_reads'] ?? false;

        $adapter = new AwsS3V3Adapter(
            new S3Client($s3Config),
            $s3Config['bucket'],
            $root,
            $options,
            $streamReads
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
}

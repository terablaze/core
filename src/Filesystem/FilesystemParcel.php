<?php

namespace TeraBlaze\Filesystem;

use TeraBlaze\Container\Container;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Filesystem\Driver\FileDriverInterface;
use TeraBlaze\Filesystem\Driver\LocalFileDriver;
use TeraBlaze\Filesystem\Exception\DriverException;

class FilesystemParcel extends Parcel implements ParcelInterface
{
    public function boot(): void
    {
        $parsed = loadConfig("filesystems");

        foreach ($parsed->get('filesystems.disks') as $key => $conf) {
            $this->initialize($key, $conf);
        }
    }

    protected function initialize(string $confKey, array $conf): void
    {
        $type = $conf['type'] ?? $conf['driver'] ?? '';

        $driverName = "filesystems.disks.{$confKey}";
        if (empty($type)) {
            throw new DriverException("Filesystem driver type not set");
        }

        switch ($type) {
            case 'local':
                $driver = new LocalFileDriver($conf);
                break;
            default:
                throw new DriverException(sprintf("Invalid or unimplemented filesystem type: %s", $type));
        }

        $this->container->registerServiceInstance($driverName, $driver);
        $this->container->setAlias("filesystems.disk.{$confKey}", $driverName);

        if (getConfig('filesystems.default') === $confKey) {
            $this->container->setAlias('files', $driverName);
            $this->container->setAlias(FileDriverInterface::class, $driverName);
        }
        if (getConfig('filesystems.cloud') === $confKey) {
            $this->container->setAlias('cloud', $driverName);
        }
    }
}

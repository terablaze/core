<?php

namespace TeraBlaze\Database\Console\Command\Migrations;

use TeraBlaze\Core\Console\Command;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Database\Connection\ConnectionInterface;

class BaseCommand extends Command
{
    /**
     * Get all of the migration paths.
     *
     * @return array
     */
    protected function getMigrationPaths()
    {
        $paths[] = $this->getAppMigrationPath();
        foreach ($this->getKernel()->getParcels() as $parcel) {
            if ($parcel instanceof ParcelInterface) {
                $paths[] = $parcel->getPath() . DIRECTORY_SEPARATOR
                    . "database" . DIRECTORY_SEPARATOR . "migrations";
            }
        }
        return $paths;
    }

    protected function getMigrations()
    {
        $paths = $this->getMigrationPaths();
        $pattern = '*.php';
        $migrationFiles = [];
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $subMigrationFiles = glob("{$path}/{$pattern}");
            if (count($subMigrationFiles) < 1) {
                continue;
            }
            $migrationFiles = [...$subMigrationFiles];
        }

        sort($migrationFiles, SORT_ASC);
        return $migrationFiles;
    }


    /**
     * Get the path to the migration directory.
     *
     * @return string
     */
    protected function getAppMigrationPath()
    {
        return $this->getKernel()->getProjectDir() . DIRECTORY_SEPARATOR
            . "database" . DIRECTORY_SEPARATOR . "migrations";
    }

    protected function connection(string $name): ConnectionInterface
    {
        return $this->container->get($name);
    }
}

<?php

namespace TeraBlaze\Ripana\Database\Console\Command\Migrations;

use TeraBlaze\Core\Console\Command;
use TeraBlaze\Core\Parcel\ParcelInterface;

class BaseCommand extends Command
{
    /**
     * Get all of the migration paths.
     *
     * @return array
     */
    protected function getMigrationPaths()
    {
        $paths[] = $this->getKernel()->getProjectDir() . DIRECTORY_SEPARATOR
            . "database" . DIRECTORY_SEPARATOR . "migrations";
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
    protected function getMigrationPath()
    {
        return $this->getKernel()->getProjectDir() . DIRECTORY_SEPARATOR
            . "database" . DIRECTORY_SEPARATOR . "migrations";
    }
}

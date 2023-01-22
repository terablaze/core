<?php

namespace Terablaze\Database\Console\Command\Migrations;

use Terablaze\Console\Command;
use Terablaze\Core\Parcel\ParcelInterface;

class BaseCommand extends Command
{
    /**
     * Get all of the migration paths.
     *
     * @return array
     */
    protected function getMigrationPaths()
    {
        $paths[] = $this->getMigrationPath();
        foreach ($this->getKernel()->getParcels() as $parcel) {
            if ($parcel instanceof ParcelInterface) {
                $paths[] = $parcel->getPath() . DIRECTORY_SEPARATOR
                    . "database" . DIRECTORY_SEPARATOR . "migrations";
            }
        }
        return $paths;
    }

    /**
     * Determine if the given path(s) are pre-resolved "real" paths.
     *
     * @return bool
     */
    protected function usingRealPath()
    {
        return $this->input->hasOption('realpath') && $this->input->getOption('realpath');
    }

    /**
     * Get the path to the migration directory.
     *
     * @return string
     */
    protected function getMigrationPath()
    {
        return baseDir('database' . DIRECTORY_SEPARATOR . 'migrations');
    }
}

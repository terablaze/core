<?php

namespace TeraBlaze\Profiler\DebugBar\Storage;

use DateTime;
use DebugBar\Storage\StorageInterface;

/**
 * Stores collected data into files
 */
class FilesystemStorage implements StorageInterface
{
    protected $dirname;
    protected $gc_lifetime = 24;     // Hours to keep collected data;
    protected $gc_probability = 5;   // Probability of GC being run on a save request. (5/100)

    /**
     * @param string $dirname Directories where to store files
     */
    public function __construct($dirname)
    {
        $this->dirname = rtrim($dirname, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * {@inheritDoc}
     */
    public function save($id, $data)
    {
        if (!is_dir($this->dirname)) {
            if (makeDir($this->dirname)) {
                file_put_contents($this->dirname . '.gitignore', "*\n!.gitignore\n");
            } else {
                throw new \Exception("Cannot create directory '$this->dirname'..");
            }
        }

        try {
            file_put_contents($this->makeFilename($id), json_encode($data, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            //TODO; error handling
        }

        // Randomly check if we should collect old files
        if (rand(1, 100) <= $this->gc_probability) {
            $this->garbageCollect();
        }
    }

    /**
     * Create the filename for the data, based on the id.
     *
     * @param $id
     * @return string
     */
    public function makeFilename($id)
    {
        return $this->dirname . basename($id) . ".json";
    }

    /**
     * Delete files older then a certain age (gc_lifetime)
     */
    protected function garbageCollect()
    {
        $historyFiles = glob($this->dirname . '*.json');
        foreach ($historyFiles as $historyFile) {
            $file = new \SplFileInfo($historyFile);
            if (!$file->isFile()) {
                continue;
            }
            if ($file->getMTime() < (new DateTime($this->gc_lifetime . ' hour ago'))->getTimestamp()) {
                @unlink($file->getRealPath());
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get($id)
    {
        return json_decode(file_get_contents($this->makeFilename($id)), true);
    }

    /**
     * {@inheritDoc}
     */
    public function find(array $filters = [], $max = 20, $offset = 0)
    {
        // Sort by modified time, newest first
        $sort = function (\SplFileInfo $a, \SplFileInfo $b) {
            return strcmp($b->getMTime(), $a->getMTime());
        };

        // Loop through .json files, filter the metadata and stop when max is found.
        $i = 0;
        $results = [];
        $historyFiles = glob($this->dirname . '*.json');
        foreach ($historyFiles as $historyFile) {
            if ($i++ < $offset && empty($filters)) {
                $results[] = null;
                continue;
            }
            $data = json_decode((string) file_get_contents($historyFile), true);
            $meta = $data['__meta'];
            unset($data);
            if ($this->filter($meta, $filters)) {
                $results[] = $meta;
            }
            if (count($results) >= ($max + $offset)) {
                break;
            }
        }
        return array_slice($results, $offset, $max);
    }

    /**
     * Filter the metadata for matches.
     *
     * @param $meta
     * @param $filters
     * @return bool
     */
    protected function filter($meta, $filters)
    {
        foreach ($filters as $key => $value) {
            if (!isset($meta[$key]) || fnmatch($value, $meta[$key]) === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function clear()
    {
        $historyFiles = glob($this->dirname . '*.json');
        foreach ($historyFiles as $historyFile) {
            $file = new \SplFileInfo($historyFile);
            @unlink($file->getRealPath());
        }
    }
}

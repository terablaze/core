<?php

namespace TeraBlaze\Translation;

use TeraBlaze\Collection\ArrayCollection;
use TeraBlaze\Collection\Exceptions\TypeException;
use TeraBlaze\Filesystem\Exception\FileNotFoundException;
use TeraBlaze\Filesystem\Files;
use RuntimeException;

class FileLoader implements LoaderInterface
{
    /**
     * The filesystem instance.
     *
     * @var Files
     */
    protected Files $files;

    /**
     * The default path for the loader.
     *
     * @var string
     */
    protected string $path;

    /**
     * All of the registered paths to JSON translation files.
     *
     * @var array
     */
    protected array $jsonPaths = [];

    /**
     * All of the namespace hints.
     *
     * @var array
     */
    protected array $hints = [];

    /**
     * Create a new file loader instance.
     *
     * @param Files $files
     * @param string $path
     */
    public function __construct(Files $files, string $path)
    {
        $this->path = $path;
        $this->files = $files;
    }

    /**
     * Load the messages for the given locale.
     *
     * @param string $locale
     * @param string $group
     * @param string|null $namespace
     * @return array
     * @throws FileNotFoundException|TypeException
     */
    public function load(string $locale, string $group, ?string $namespace = null): array
    {
        if ($group === '*' && $namespace === '*') {
            return $this->loadJsonPaths($locale);
        }

        if (is_null($namespace) || $namespace === '*') {
            return $this->loadPath($this->path, $locale, $group);
        }

        return $this->loadNamespaced($locale, $group, $namespace);
    }

    /**
     * Load a namespaced translation group.
     *
     * @param string $locale
     * @param string $group
     * @param string $namespace
     * @return string[]
     * @throws FileNotFoundException
     */
    protected function loadNamespaced(string $locale, string $group, string $namespace): array
    {
        if (isset($this->hints[$namespace])) {
            $lines = $this->loadPath($this->hints[$namespace], $locale, $group);

            return $this->loadNamespaceOverrides($lines, $locale, $group, $namespace);
        }

        return [];
    }

    /**
     * Load a local namespaced translation group for overrides.
     *
     * @param string[] $lines
     * @param string $locale
     * @param string $group
     * @param string $namespace
     * @return string[]
     * @throws FileNotFoundException
     */
    protected function loadNamespaceOverrides(array $lines, string $locale, string $group, string $namespace): array
    {
        $file = "$this->path/vendor/$namespace/$locale/$group.php";

        if ($this->files->exists($file)) {
            return array_replace_recursive($lines, $this->files->getRequire($file));
        }

        return $lines;
    }

    /**
     * Load a locale from a given path.
     *
     * @param string $path
     * @param string $locale
     * @param string $group
     * @return array
     * @throws FileNotFoundException
     */
    protected function loadPath(string $path, string $locale, string $group): array
    {
        if ($this->files->exists($full = "$path/$locale/$group.php")) {
            return $this->files->getRequire($full);
        }

        return [];
    }

    /**
     * Load a locale from the given JSON file path.
     *
     * @param string $locale
     * @return array
     *
     * @throws RuntimeException|FileNotFoundException|TypeException
     */
    protected function loadJsonPaths(string $locale): array
    {
        return (new ArrayCollection(array_merge($this->jsonPaths, [$this->path])))
            ->reduce(function ($output, $path) use ($locale) {
                if ($this->files->exists($full = "$path/$locale.json")) {
                    $decoded = json_decode($this->files->get($full), true);

                    if (is_null($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                        throw new RuntimeException("Translation file [$full] contains an invalid JSON structure.");
                    }

                    $output = array_merge($output, $decoded);
                }

                return $output;
            }, []);
    }

    /**
     * Add a new namespace to the loader.
     *
     * @param string $namespace
     * @param string $hint
     * @return void
     */
    public function addNamespace(string $namespace, string $hint): void
    {
        $this->hints[$namespace] = $hint;
    }

    /**
     * Add a new JSON path to the loader.
     *
     * @param string $path
     * @return void
     */
    public function addJsonPath(string $path): void
    {
        $this->jsonPaths[] = $path;
    }

    /**
     * Get an array of all the registered namespaces.
     *
     * @return string[]
     */
    public function namespaces(): array
    {
        return $this->hints;
    }
}

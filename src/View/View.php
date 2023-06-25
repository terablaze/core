<?php

namespace Terablaze\View;

use Closure;
use Exception;
use Terablaze\Container\Container;
use Terablaze\Core\Kernel\KernelInterface;
use Terablaze\EventDispatcher\Dispatcher;
use Terablaze\Support\StringMethods;
use Terablaze\View\Engine\EngineInterface;
use Terablaze\View\Events\TemplateEvent;
use Terablaze\View\Exception\NamespaceNotRegisteredException;
use Terablaze\View\Exception\TemplateNotFoundException;
use Throwable;

class View
{
    protected Container $container;
    protected KernelInterface $kernel;
    protected string $cachePath;

    /** @var string[] $paths */
    protected array $paths = [];

    /** @var array<string, mixed> $namespacedPaths */
    protected static array $namespacedPaths = [];

    /** @var array<string, EngineInterface> $engines */
    protected array $engines = [];

    /** @var Closure[] $macros */
    protected array $macros = [];

    public function __construct(Container $container, KernelInterface $kernel)
    {
        $this->kernel = $kernel;
        $this->container = $container;
        $this->cachePath = $this->kernel->getCacheDir() . 'views';
    }

    public function addPath(string $path): self
    {
        array_push($this->paths, $path);
        return $this;
    }

    /**
     * @param string $namespace
     * @param string[] $path
     */
    public static function addNamespacedPaths(string $namespace, array $path): void
    {
        static::$namespacedPaths[$namespace] = $path;
    }

    /**
     * @param string $namespace
     * @return string[]
     */
    public static function getNamespacedPaths(string $namespace): array
    {
        if (array_key_exists($namespace, static::$namespacedPaths)) {
            return static::$namespacedPaths[$namespace];
        }
        return [];
    }

    public function addEngine(string $extension, EngineInterface $engine): self
    {
        $this->engines[$extension] = $engine;
        $this->engines[$extension]->setManager($this);
        return $this;
    }

    /**
     * @param string $template
     * @param array<string, mixed> $data
     * @return Template
     * @throws NamespaceNotRegisteredException
     * @throws TemplateNotFoundException
     */
    public function make(string $template, array $data = []): Template
    {
        return $this->render($template, $data);
    }

    /**
     * @param string $template
     * @param array<string, mixed> $data
     * @return Template
     * @throws NamespaceNotRegisteredException
     * @throws TemplateNotFoundException
     */
    public function render(string $template, array $data = []): Template
    {
        $name = $template;
        [
            'paths' => $paths,
            'template' => $template,
            'namespace' => $namespace
        ] = $this->resolvePathAndTemplate($template);
        foreach ($this->engines as $extension => $engine) {
            foreach ($paths as $path) {
                $file = normalizeDir("$path" . DIRECTORY_SEPARATOR . "$template");
                if (is_file($file) && StringMethods::endsWith($template, $extension)) {
                    return $this->makeTemplate($engine, $name, $file, $data, $namespace);
                }

                $fileWithExtension = "$file.$extension";
                if (is_file($fileWithExtension)) {
                    return $this->makeTemplate($engine, $name, $fileWithExtension, $data, $namespace);
                }
            }
        }

        throw new TemplateNotFoundException("Could not find template: '$template'");
    }

    public function includeFile(string $template): string
    {
        $name = $template;
        [
            'paths' => $paths,
            'template' => $template,
            'namespace' => $namespace
        ] = $this->resolvePathAndTemplate($template);
        foreach ($this->engines as $extension => $engine) {
            foreach ($paths as $path) {
                $file = normalizeDir("$path" . DIRECTORY_SEPARATOR . "$template");
                if (is_file($file) && StringMethods::endsWith($template, $extension)) {
                    $templateS = new Template($engine, $name, $file, [], $namespace);
                    $this->container->get(Dispatcher::class)->dispatch(new TemplateEvent($templateS));
                    return file_get_contents($file);
                }

                $fileWithExtension = "$file.$extension";
                if (is_file($fileWithExtension)) {
                    $templateS = new Template($engine, $name, $fileWithExtension, [], $namespace);
                    $this->container->get(Dispatcher::class)->dispatch(new TemplateEvent($templateS));
                    return file_get_contents($fileWithExtension);
                }
            }
        }

        throw new TemplateNotFoundException("Could not find template: '$template'");
    }

    public function addMacro(string $name, Closure $closure): self
    {
        $this->macros[$name] = $closure;
        return $this;
    }

    public function useMacro(string $name, ...$values)
    {
        if (isset($this->macros[$name])) {
            $bound = $this->macros[$name]->bindTo($this);
            return $bound(...$values);
        }

        throw new Exception("Macro isn't defined: '$name'");
    }

    public function shouldCache(): bool
    {
        return getConfig('view.should_cache', !$this->kernel->isDebug());
    }

    public function getCachePath(): string
    {
        makeDir($this->cachePath);
        return $this->cachePath;
    }

    public function setCachePath(string $cachePath): self
    {
        if (!empty($cachePath)) {
            $this->cachePath = $cachePath;
        }
        makeDir($this->cachePath);
        return $this;
    }

    /**
     * @param string $template
     * @return array<string, string[]|string>;
     * @throws NamespaceNotRegisteredException
     */
    private function resolvePathAndTemplate(string $template): array
    {
        $namespace = '';
        if (StringMethods::contains($template, "::")) {
            $namespace = StringMethods::before($template, "::");
            if (!array_key_exists($namespace, static::$namespacedPaths)) {
                throw new NamespaceNotRegisteredException(
                    sprintf(
                        'The view namespace: "%s" you are trying to use in "%s" has not been registered',
                        $namespace,
                        $template
                    )
                );
            }

            $template = StringMethods::after($template, "::");

            $paths = static::$namespacedPaths[$namespace];
        } else {
            $paths = $this->paths;
        }

        return compact('paths', 'template', 'namespace');
    }

    /**
     * @param EngineInterface $engine
     * @param string $name
     * @param $file
     * @param array $data
     * @param $namespace
     * @return Template
     */
    private function makeTemplate(EngineInterface $engine, string $name, $file, array $data, $namespace): Template
    {
        $template = new Template($engine, $name, $file, $data, $namespace);
        try {
            $this->container->get(Dispatcher::class)->dispatch(new TemplateEvent($template));
        } catch (Throwable $e) {
        }
        return $template;
    }
}

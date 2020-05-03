<?php

declare(strict_types=1);

namespace App;

use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Relay\RelayBuilder;
use TeraBlaze\Container\Container;

class Kernel
{
    private $booted = false;
    private $debug;
    private $environment;
    private $projectDir;

    /** @var Container */
    private $container;

    public function __construct(string $env, bool $debug = false)
    {
        $this->debug = $debug;
        $this->environment = $env;
    }

    /**
     * Handle a Request and turn it in to a response.
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws \ReflectionException
     */
    public function handle(RequestInterface $request): ResponseInterface
    {
        $this->boot();

        $middlewares[] = $this->container->get('middleware.cache');
        $middlewares[] = new \App\Middleware\Router();

        $runner = (new RelayBuilder())->newInstance($middlewares);

        return $runner($request, new Response());
    }

    public function boot()
    {
        if ($this->booted) {
            return;
        }

        $containerDumpFile = $this->getProjectDir() . '/var/cache/' . $this->environment . '/container.php';
        if (!$this->debug && file_exists($containerDumpFile)) {
            require_once $containerDumpFile;
            $container = new \CachedContainer();
        } else {
            $container = Container::createContainer();
            $container->setParameter('kernel.project_dir', $this->getProjectDir());
            $container->setParameter('kernel.environment', $this->environment);
            $loader = new YamlFileLoader($container, new FileLocator($this->getProjectDir() . '/config'));
            try {
                $loader->load('services.yaml');
                $loader->load('services_' . $this->environment . '.yaml');
            } catch (FileLocatorFileNotFoundException $e) {
            }
            $container->compile();
        }

        $this->container = $container;
        $this->booted = true;
    }

    private function getProjectDir()
    {
        if (null === $this->projectDir) {
            $r = new \ReflectionObject($this);

            if (!file_exists($dir = $r->getFileName())) {
                throw new \LogicException(sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name));
            }

            $dir = $rootDir = \dirname($dir);
            while (!file_exists($dir . '/composer.json')) {
                if ($dir === \dirname($dir)) {
                    return $this->projectDir = $rootDir;
                }
                $dir = \dirname($dir);
            }
            $this->projectDir = $dir;
        }

        return $this->projectDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getContainer()
    {
        if (!$this->container) {
            throw new \LogicException('Cannot retrieve the container from a non-booted kernel.');
        }

        return $this->container;
    }

    public function reboot()
    {
        $this->shutdown();
        $this->boot();
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown()
    {
        if (false === $this->booted) {
            return;
        }

        $this->booted = false;
        $this->container = null;
//        $this->requestStackSize = 0;
//        $this->resetServices = false;
    }
}
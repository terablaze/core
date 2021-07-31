<?php

namespace TeraBlaze\Core\Kernel;

use Psr\EventDispatcher\EventDispatcherInterface;
use TeraBlaze\Config\ConfigInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\HttpBase\Request;

interface KernelInterface extends HttpKernelInterface
{

    /**
     * Boots the current kernel.
     */
    public function boot(): void;

    /**
     * Returns the global config
     *
     * @return ConfigInterface
     */
    public function getConfig(): ConfigInterface;

    /**
     * Shutdowns the kernel.
     *
     * This method is mainly useful when doing functional testing.
     */
    public function shutdown(): void;

    /**
     * Gets the environment.
     *
     * @return string The current environment
     */
    public function getEnvironment(): string;

    /**
     * Checks if debug mode is enabled.
     *
     * @return bool true if debug mode is enabled, false otherwise
     */
    public function isDebug(): bool;

    /**
     * Gets the current container.
     *
     * @return ContainerInterface|Container
     */
    public function getContainer(): ContainerInterface;

    /**
     * Gets the project dir (path of the project's composer file).
     *
     * @return string
     */
    public function getProjectDir(): string;

    public function setConfigDir(string $configDir): KernelInterface;

    public function getConfigDir(): string;

    public function setEnvConfigDir(string $envConfigDir): KernelInterface;

    public function getEnvConfigDir(): string;

    public function getVarDir(): string;

    public function getCacheDir(): string;

    public function getLogsDir(): string;

    public function getSessionsDir(): string;

    /**
     * Returns an array of parcels to register.
     *
     * @return iterable|ParcelInterface[] An iterable of parcel instances
     */
    public function registerParcels(): iterable;

    /**
     * Gets the registered parcel instances.
     *
     * @return ParcelInterface[] An array of registered parcel instances
     */
    public function getParcels(): array;

    /**
     * Returns a parcel.
     *
     * @return ParcelInterface A ParcelInterface instance
     *
     * @throws \InvalidArgumentException when the parcel is not enabled
     */
    public function getParcel(string $name): ParcelInterface;

    public function registerMiddleWares(): void;

    public function registerMiddleWare(string $class): void;

    public function getInitialRequest(): ?Request;

    public function getCurrentRequest(): ?Request;

    public function getEventDispatcher(): EventDispatcherInterface;
}

<?php

namespace Terablaze\Core\Kernel;

use Psr\EventDispatcher\EventDispatcherInterface;
use Terablaze\Config\ConfigInterface;
use Terablaze\Container\Container;
use Terablaze\Container\ContainerInterface;
use Terablaze\Core\MaintenanceMode\MaintenanceModeInterface;
use Terablaze\Core\Parcel\ParcelInterface;
use Terablaze\ErrorHandler\Exception\Http\HttpException;
use Terablaze\ErrorHandler\Exception\Http\NotFoundHttpException;
use Terablaze\ErrorHandler\ExceptionHandlerInterface;
use Terablaze\HttpBase\Request;

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
     * Get an instance of the maintenance mode manager implementation.
     *
     * @return MaintenanceModeInterface
     */
    public function maintenanceMode();

    /**
     * Determine if the application is currently down for maintenance.
     *
     * @return bool
     */
    public function isDownForMaintenance();

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
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function inConsole(): bool;

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

    public function getStorageDir(): string;

    public function getCacheDir(): string;

    public function getLogsDir(): string;

    public function getSessionsDir(): string;

    public function resourceDir($path = ''): string;

    /**
     * Join the given paths together.
     *
     * @param  string  $basePath
     * @param  string  $path
     * @return string
     */
    public function joinPaths($basePath, $path = ''): string;

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

    public function registerMiddleWare(string $class, ?string $name = null): void;

    public function getInitialRequest(): ?Request;

    public function getCurrentRequest(): ?Request;

    public function getEventDispatcher(): EventDispatcherInterface;

    /**
     * Throw an HttpException with the given data.
     *
     * @param  int  $code
     * @param  string  $message
     * @param  string[] $headers
     * @return void
     *
     * @throws HttpException
     * @throws NotFoundHttpException
     */
    public function abort(int $code, string $message = '', array $headers = []): void;

    /**
     * Get an instance of the exception handler.
     *
     * @return ExceptionHandlerInterface
     */
    public function getExceptionHandler();

    public function terminating(\Closure $param);
}

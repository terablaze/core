<?php

namespace TeraBlaze\Routing;

use ReflectionException;
use TeraBlaze\Config\Exception\InvalidContextException;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;

class RoutingParcel extends Parcel implements ParcelInterface
{
    /**
     * @throws InvalidContextException
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $routes = loadConfigArray('routes');

        $this->initialize($routes);
    }

    /**
     * @param array<string|int, mixed> $routes
     * @throws ReflectionException
     */
    private function initialize(array $routes): void
    {
        $this->registerRoutes($routes);
        $this->container->make(RouterMiddleware::class);
    }
}

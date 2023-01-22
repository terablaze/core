<?php

namespace Terablaze\Routing;

use ReflectionException;
use Terablaze\Config\Exception\InvalidContextException;
use Terablaze\Core\Parcel\Parcel;
use Terablaze\Core\Parcel\ParcelInterface;

class RoutingParcel extends Parcel implements ParcelInterface
{
    /**
     * @throws InvalidContextException
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $this->container->make(RouterInterface::class, [
            'class' => Router::class,
            'alias' => 'routing'
        ]);

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
        $this->getKernel()->registerMiddleWare(RouterMiddleware::class);
    }
}

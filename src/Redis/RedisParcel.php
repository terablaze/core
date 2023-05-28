<?php

namespace Terablaze\Redis;

use Terablaze\Core\Parcel\Parcel;
use Terablaze\Core\Parcel\ParcelInterface;
use Terablaze\Support\ArrayMethods;

class RedisParcel extends Parcel implements ParcelInterface
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
        $parsed = $this->loadConfig('redis')->get('redis');

        $this->container->registerServiceInstance(
            'redis',
            new RedisManager(
                $this->container,
                ArrayMethods::pull($parsed, 'client', 'phpredis'),
                $parsed
            )
        );

        $this->container->registerServiceInstance(
            'redis.connection',
            $this->container->get('redis')->connection()
        );
    }
}

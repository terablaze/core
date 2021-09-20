<?php

namespace TeraBlaze\Log;

use Psr\Log\LoggerInterface;
use ReflectionException;
use TeraBlaze\Config\Exception\InvalidContextException;
use TeraBlaze\Core\Parcel\Parcel;

class LogParcel extends Parcel
{
    /**
     * Register the service provider.
     *
     * @return void
     * @throws ReflectionException
     * @throws InvalidContextException
     */
    public function boot(): void
    {
        $config = loadConfig('logging');
        $this->container->make('log', [
            'class' => LogManager::class,
            'arguments' => [
                'config' => $config,
            ],
            'alias' => LoggerInterface::class
        ]);
    }
}

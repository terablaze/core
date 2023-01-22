<?php

namespace Terablaze\Log;

use Psr\Log\LoggerInterface;
use ReflectionException;
use Terablaze\Config\Exception\InvalidContextException;
use Terablaze\Core\Parcel\Parcel;

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

<?php

namespace Terablaze\Bus\Traits;

use Terablaze\Bus\Dispatcher;
use Terablaze\Container\Container;

trait GetDispatcher
{
    protected static function getDispatcher()
    {
        static $dispatcher;
        if (!$dispatcher) {
            try {
                $dispatcher = Container::getContainer()->make(Dispatcher::class);
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    'Unable to resolve the dispatcher from the service container. Please register the BusParcel',
                    is_int($e->getCode()) ? $e->getCode() : 0, $e
                );
            }
        }

        return $dispatcher;
    }
}

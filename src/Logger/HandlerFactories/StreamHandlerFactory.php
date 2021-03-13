<?php

namespace TeraBlaze\Logger\HandlerFactories;

use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ReflectionException;
use TeraBlaze\Configuration\Configuration;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Core\Kernel\Kernel;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Events\Events;
use TeraBlaze\Ripana\Database\Drivers\Mysqli\Connector;
use TeraBlaze\Ripana\Database\Exception\Argument;
use TeraBlaze\Ripana\ORM\EntityManager;

class StreamHandlerFactory
{
    private StreamHandler $handler;

    public function __construct(Container $container, array $options = [], string $defaultLevel = Logger::DEBUG)
    {
        /** @var Kernel $kernel */
        $kernel = $container->get('kernel');
        $dir = ($options['path'] ?? $kernel->getLogsDir());
        $file = ($options['file'] ?? 'app.log');
        $level = $options['level'] ?? $defaultLevel;
        $this->handler = new StreamHandler($dir . $file, $level);
        if (!empty($options['formatter'])) {
            $this->handler->setFormatter(new $options['formatter']());
        }
    }

    public function getHandler(): StreamHandler
    {
        if (!$this->handler instanceof StreamHandler) {
            throw new Exception('Handler not initialised');
        }
        return $this->handler;
    }
}

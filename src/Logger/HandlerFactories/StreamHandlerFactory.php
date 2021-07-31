<?php

namespace TeraBlaze\Logger\HandlerFactories;

use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Kernel\Kernel;

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
            $formatter = $options['formatter'];
            if (is_array($formatter)) {
                $formatterClass = $formatter[0];
                $formatterOptions = $formatter[1] ?? [];
                $this->handler->setFormatter(new $formatterClass(...$formatterOptions));
            } else {
                $this->handler->setFormatter(new $formatter());
            }
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

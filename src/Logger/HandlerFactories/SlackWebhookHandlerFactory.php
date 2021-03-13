<?php

namespace TeraBlaze\Logger\HandlerFactories;

use Exception;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Logger;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Kernel\Kernel;

class SlackWebhookHandlerFactory
{
    private SlackWebhookHandler $handler;

    public function __construct(Container $container, array $options = [], string $defaultLevel = Logger::DEBUG)
    {
        /** @var Kernel $kernel */
        $kernel = $container->get('kernel');
        if (empty($options['url'])) {
            throw new Exception('Slack webhook url not set');
        }
        $url = ($options['url']);
        $slackChannel = $options['channel'];
        $botName = $options['username'] ?? "TeraBlaze Logger";
        $level = $options['level'] ?? $defaultLevel;
        $this->handler = new SlackWebhookHandler($url, $slackChannel, $botName);
        $this->handler->setLevel($level);
        $this->handler->setFormatter(new $options['formatter']());
    }

    public function getHandler(): SlackWebhookHandler
    {
        if (! $this->handler instanceof SlackWebhookHandler) {
            throw new Exception('Handler not initialised');
        }
        return $this->handler;
    }
}

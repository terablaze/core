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
        $useAttachment = $options['useAttachment'] ?? true;
        $iconEmoji = $options['iconEmoji'] ?? null;
        $useShortAttachment = $options['useShortAttachment'] ?? false;
        $includeContextAndExtra = $options['includeContextAndExtra'] ?? true;
        $level = $options['level'] ?? $defaultLevel;
        $bubble = $options['bubble'] ?? true;
        $excludedFields = $options['excludedFields'] ?? [];
        $this->handler = new SlackWebhookHandler(
            $url,
            $slackChannel,
            $botName,
            $useAttachment,
            $iconEmoji,
            $useShortAttachment,
            $includeContextAndExtra,
            $level,
            $bubble,
            $excludedFields
        );
        if (!empty($options['formatter'])) {
            $this->handler->setFormatter(new $options['formatter']());
        }
    }

    public function getHandler(): SlackWebhookHandler
    {
        if (!$this->handler instanceof SlackWebhookHandler) {
            throw new Exception('Handler not initialised');
        }
        return $this->handler;
    }
}

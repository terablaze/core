<?php

namespace TeraBlaze\Logger;

use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use TeraBlaze\Config\Driver\DriverInterface;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Logger\HandlerFactories\SlackWebhookHandlerFactory;
use TeraBlaze\Logger\HandlerFactories\StreamHandlerFactory;

class LoggerParcel extends Parcel implements ParcelInterface
{
    public function boot(): void
    {
        if (!$this->container->has('configuration')) {
            return;
        }
        /** @var DriverInterface $configuration */
        $configuration = $this->container->get('configuration');

        $parsed = $configuration->parseArray("logging");

        foreach ($parsed as $channel => $conf) {
            if (!empty($conf)) {
                $options = $conf;
                $this->initialize($channel, $options);
            }
        }
    }

    /**
     * @param string $logChannel
     */
    public function initialize(string $logChannel = "default", $options = [])
    {
        $channelName = "logger.{$logChannel}";
        $logger = new Logger($logChannel);
        foreach ($options['handlers'] as $handler) {
            switch ($handler['handler']) {
                case 'stream':
                case 'streamhandler':
                case StreamHandler::class:
                    $logger->pushHandler((new StreamHandlerFactory(
                        $this->container,
                        $handler,
                        $options['level']
                    ))->getHandler());
                    break;
                case 'slackwebhook':
                case 'slackwebhookhandler':
                case SlackWebhookHandler::class:
                    $logger->pushHandler((new SlackWebhookHandlerFactory(
                        $this->container,
                        $handler,
                        $options['level']
                    ))->getHandler());
                    break;
            }
        }
        $this->container->registerServiceInstance($channelName, $logger);
        if ($logChannel == 'default') {
            $this->container->setAlias(LoggerInterface::class, $channelName);
        }
    }
}

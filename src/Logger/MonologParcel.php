<?php

namespace TeraBlaze\Logger;

use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use TeraBlaze\Configuration\Driver\DriverInterface;
use TeraBlaze\Configuration\Exception\Argument as ArgumentException;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Logger\HandlerFactories\SlackWebhookHandlerFactory;
use TeraBlaze\Logger\HandlerFactories\StreamHandlerFactory;

class MonologParcel extends Parcel implements ParcelInterface
{
    /** @var Container $container */
    private $container;

    /**
     * @param ContainerInterface|null $container
     */
    public function build(?ContainerInterface $container)
    {
        $this->container = $container;

        if (!$this->container->has('configuration')) {
            return;
        }
        /** @var DriverInterface $configuration */
        $configuration = $this->container->get('configuration');

        try {
            $parsed = $configuration->parseArray("monolog");
        } catch (ArgumentException $argumentException) {
            $parsed = $configuration->parseArray("logging");
        }

        foreach ($parsed as $channel => $conf) {
            if (!empty($parsed[$channel])) {
                $options = $parsed[$channel];
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

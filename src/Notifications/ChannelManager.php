<?php

namespace Terablaze\Notifications;

use Terablaze\Bus\Dispatcher as Bus;
use Terablaze\Container\ContainerInterface;
use Terablaze\EventDispatcher\Dispatcher;
use Terablaze\Notifications\DispatcherInterface;
use Terablaze\Notifications\ChannelMangerInterface;
use InvalidArgumentException;
use Terablaze\Support\Manager;

class ChannelManager extends Manager implements DispatcherInterface, ChannelMangerInterface
{
    /**
     * The default channel used to deliver messages.
     *
     * @var string
     */
    protected $defaultChannel = 'mail';

    /**
     * The locale used when sending notifications.
     *
     * @var string|null
     */
    protected $locale;

    /**
     * Send the given notification to the given notifiable entities.
     *
     * @param  \Terablaze\Collection\CollectionInterface|array|mixed  $notifiables
     * @param  mixed  $notification
     * @return void
     */
    public function send($notifiables, $notification)
    {
        (new NotificationSender(
            $this, $this->container->make(Bus::class), $this->container->make(Dispatcher::class), $this->locale)
        )->send($notifiables, $notification);
    }

    /**
     * Send the given notification immediately.
     *
     * @param  \Terablaze\Collection\CollectionInterface|array|mixed  $notifiables
     * @param  mixed  $notification
     * @param  array|null  $channels
     * @return void
     */
    public function sendNow($notifiables, $notification, array $channels = null)
    {
        (new NotificationSender(
            $this, $this->container->make(Bus::class), $this->container->make(Dispatcher::class), $this->locale)
        )->sendNow($notifiables, $notification, $channels);
    }

    /**
     * Get a channel instance.
     *
     * @param  string|null  $name
     * @return mixed
     */
    public function channel($name = null)
    {
        return $this->driver($name);
    }

    /**
     * Create an instance of the database driver.
     *
     * @return \Terablaze\Notifications\Channels\DatabaseChannel
     */
    protected function createDatabaseDriver()
    {
        return $this->container->make(Channels\DatabaseChannel::class);
    }

    /**
     * Create an instance of the mail driver.
     *
     * @return \Terablaze\Notifications\Channels\MailChannel
     */
    protected function createMailDriver()
    {
        return $this->container->make(Channels\MailChannel::class);
    }

    /**
     * Create a new driver instance.
     *
     * @param  string  $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createDriver($driver)
    {
        try {
            return parent::createDriver($driver);
        } catch (InvalidArgumentException $e) {
            if (class_exists($driver)) {
                return $this->container->make($driver);
            }

            throw $e;
        }
    }

    /**
     * Get the default channel driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->defaultChannel;
    }

    /**
     * Get the default channel driver name.
     *
     * @return string
     */
    public function deliversVia()
    {
        return $this->getDefaultDriver();
    }

    /**
     * Set the default channel driver name.
     *
     * @param  string  $channel
     * @return void
     */
    public function deliverVia($channel)
    {
        $this->defaultChannel = $channel;
    }

    /**
     * Set the locale of notifications.
     *
     * @param  string  $locale
     * @return $this
     */
    public function locale($locale)
    {
        $this->locale = $locale;

        return $this;
    }
}

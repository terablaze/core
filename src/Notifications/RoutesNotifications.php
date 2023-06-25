<?php

namespace Terablaze\Notifications;

use Terablaze\Notifications\DispatcherInterface;
use Terablaze\Support\Helpers;
use Terablaze\Support\StringMethods;

trait RoutesNotifications
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $instance
     * @return void
     */
    public function notify($instance)
    {
        Helpers::container()->get(DispatcherInterface::class)->send($this, $instance);
    }

    /**
     * Send the given notification immediately.
     *
     * @param  mixed  $instance
     * @param  array|null  $channels
     * @return void
     */
    public function notifyNow($instance, array $channels = null)
    {
        Helpers::container()->get(DispatcherInterface::class)->sendNow($this, $instance, $channels);
    }

    /**
     * Get the notification routing information for the given driver.
     *
     * @param  string  $driver
     * @param  \Terablaze\Notifications\Notification|null  $notification
     * @return mixed
     */
    public function routeNotificationFor($driver, $notification = null)
    {
        if (method_exists($this, $method = 'routeNotificationFor'.StringMethods::studly($driver))) {
            return $this->{$method}($notification);
        }

        return match ($driver) {
            'database' => DatabaseNotification::class,
            'mail' => $this->getEmail() ?? $this->email, // TODO: Look into email fetching implementation
            default => null,
        };
    }
}

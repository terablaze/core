<?php

namespace Terablaze\Notifications;

interface DispatcherInterface
{
    /**
     * Send the given notification to the given notifiable entities.
     *
     * @param  \Terablaze\Collection\CollectionInterface|array|mixed  $notifiables
     * @param  mixed  $notification
     * @return void
     */
    public function send($notifiables, $notification);

    /**
     * Send the given notification immediately.
     *
     * @param  \Terablaze\Collection\CollectionInterface|array|mixed  $notifiables
     * @param  mixed  $notification
     * @return void
     */
    public function sendNow($notifiables, $notification);
}

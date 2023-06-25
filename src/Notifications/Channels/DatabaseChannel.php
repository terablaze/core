<?php

namespace Terablaze\Notifications\Channels;

use Terablaze\Notifications\DatabaseNotification;
use Terablaze\Notifications\Notification;
use RuntimeException;
use Terablaze\Support\Helpers;

class DatabaseChannel
{
    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param \Terablaze\Notifications\Notification $notification
     * @return \Terablaze\Database\ORM\Model
     */
    public function send($notifiable, Notification $notification)
    {
        /** @var DatabaseNotification $databaseNotification */
        $databaseNotification = $notifiable->routeNotificationFor('database', $notification)::create(
            $this->buildPayload($notifiable, $notification)
        );
        return $databaseNotification;
    }

    /**
     * Build an array payload for the DatabaseNotification Model.
     *
     * @param mixed $notifiable
     * @param \Terablaze\Notifications\Notification $notification
     * @return array
     */
    protected function buildPayload($notifiable, Notification $notification)
    {
        return [
            'id' => $notification->id,
            'type' => method_exists($notification, 'databaseType')
                ? $notification->databaseType($notifiable)
                : get_class($notification),
            'notifiable_type' => $notifiable->_getClassMetadata()->name,
            'notifiable_id' => $notifiable->{$notifiable->_getPrimaryColumn()['primaryProperty'] ?? 'id'},
            'data' => Helpers::jsonEncode($this->getData($notifiable, $notification)),
            'read_at' => null,
        ];
    }

    /**
     * Get the data for the notification.
     *
     * @param mixed $notifiable
     * @param \Terablaze\Notifications\Notification $notification
     * @return array
     *
     * @throws \RuntimeException
     */
    protected function getData($notifiable, Notification $notification)
    {
        if (method_exists($notification, 'toDatabase')) {
            return is_array($data = $notification->toDatabase($notifiable))
                ? $data : $data->data;
        }

        if (method_exists($notification, 'toArray')) {
            return $notification->toArray($notifiable);
        }

        throw new RuntimeException('Notification is missing toDatabase / toArray method.');
    }
}

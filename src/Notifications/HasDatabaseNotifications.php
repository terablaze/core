<?php

namespace Terablaze\Notifications;

trait HasDatabaseNotifications
{
    /**
     * Get the entity's notifications.
     *
     * @return \Terablaze\Database\Query\QueryBuilderInterface
     */
    public function notifications()
    {
        return DatabaseNotification::query()
            ->where('notifiable_type = :notifiable_type AND notifiable_is = :notifiable_id')
            ->setParameters([
                "notifiable_type" => $this->_getClassMetadata()->name,
                "notifiable_id" => $this->{$this->_getPrimaryColumn()['primaryProperty'] ?? 'id'}
            ]);
    }

    /**
     * Get the entity's read notifications.
     *
     * @return \Terablaze\Database\Query\QueryBuilderInterface
     */
    public function readNotifications()
    {
        return $this->notifications()
            ->andWhere('read_at != :scopeRead')
            ->setParameter('scopeRead', null);
    }

    /**
     * Get the entity's unread notifications.
     *
     * @return \Terablaze\Database\Query\QueryBuilderInterface
     */
    public function unreadNotifications()
    {
        return $this->notifications()
            ->andWhere('read_at = :scopeUnread')
            ->setParameter('scopeUnread', null);
    }
}

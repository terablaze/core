<?php

namespace Terablaze\Notifications;

use Carbon\Carbon;
use DateTime;
use Terablaze\Database\Query\QueryBuilderInterface;
use Terablaze\Database\ORM\Model;
use Terablaze\Database\ORM\Mapping;

/**
 * @Mapping\Table(name="notifications")
 */
class DatabaseNotification extends Model
{
    /**
     * @var string
     * @Mapping\Column(name="id", type="string", length=255)
     * @Mapping\Id
     */
    public string $id;

    /**
     * @var string
     * @Mapping\Column(name="type", type="string", length=255)
     */
    public string $type;

    /**
     * @var string
     * @Mapping\Column(name="notifiable_type", type="string", length=255)
     */
    public string $notifiableType;

    /**
     * @var string
     * @Mapping\Column(name="notifiable_id")
     */
    public string $notifiableId;

    /**
     * @var string
     * @Mapping\Column(name="data", type="string")
     */
    public string $data;

    /**
     * @var ?DateTime
     * @Mapping\Column(name="read_at", type="datetime", nullable=true, options={"default"=null})
     */
    public ?DateTime $readAt;

    /**
     * @var DateTime
     * @Mapping\Column(name="created_at", type="datetime", options={"default"="now"})
     */
    public DateTime $createdAt;

    /**
     * @var DateTime
     * @Mapping\Column(name="updated_at", type="datetime", options={"default"="now"})
     */
    public DateTime $updatedAt;

    /**
     * Get the notifiable entity that the notification belongs to.
     *
     * @return \Terablaze\Database\ORM\Model
     */
    public function notifiable()
    {
        return $this->notifiable_type::find($this->notifiable_id);
    }

    /**
     * Mark the notification as read.
     *
     * @return void
     */
    public function markAsRead()
    {
        if (is_null($this->read_at)) {
            $this->read_at =  Carbon::now();
            $this->save();
        }
    }

    /**
     * Mark the notification as unread.
     *
     * @return void
     */
    public function markAsUnread()
    {
        if (! is_null($this->read_at)) {
            $this->read_at = null;
            $this->save();
        }
    }

    /**
     * Determine if a notification has been read.
     *
     * @return bool
     */
    public function read()
    {
        return $this->read_at !== null;
    }

    /**
     * Determine if a notification has not been read.
     *
     * @return bool
     */
    public function unread()
    {
        return $this->read_at === null;
    }

    /**
     * Scope a query to only include read notifications.
     *
     * @param  \Terablaze\Database\Query\QueryBuilderInterface  $query
     * @return \Terablaze\Database\Query\QueryBuilderInterface
     */
    public function scopeRead(QueryBuilderInterface $query)
    {
        return $query->andWhere('read_at != :scopeRead')->setParameter('scopeRead', null);
    }

    /**
     * Scope a query to only include unread notifications.
     *
     * @param  \Terablaze\Database\Query\QueryBuilderInterface  $query
     * @return \Terablaze\Database\Query\QueryBuilderInterface
     */
    public function scopeUnread(QueryBuilderInterface $query)
    {
        return $query->andWhere('read_at = :scopeUnread')->setParameter('scopeUnread', null);
    }

    /**
     * Create a new database notification collection instance.
     *
     * @param  array  $models
     * @return \Terablaze\Notifications\DatabaseNotificationCollection
     */
    public function newCollection(array $models = [])
    {
        return new DatabaseNotificationCollection($models);
    }
}

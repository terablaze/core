<?php

namespace Terablaze\Notifications;

use Terablaze\Bus\Traits\QueueableTrait;
use Terablaze\Collection\ArrayCollection;
use Terablaze\Queue\ShouldBeEncrypted;
use Terablaze\Queue\ShouldQueue;
use Terablaze\Database\ORM\EntityCollection;
use Terablaze\Database\ORM\Model;
use Terablaze\Queue\InteractsWithQueue;
use Terablaze\Queue\SerializesModels;
use Terablaze\Collection\CollectionInterface;

class SendQueuedNotifications implements ShouldQueue
{
    use InteractsWithQueue, QueueableTrait, SerializesModels;

    /**
     * The notifiable entities that should receive the notification.
     *
     * @var \Terablaze\Collection\CollectionInterface
     */
    public $notifiables;

    /**
     * The notification to be sent.
     *
     * @var \Terablaze\Notifications\Notification
     */
    public $notification;

    /**
     * All of the channels to send the notification to.
     *
     * @var array
     */
    public $channels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions;

    /**
     * Indicates if the job should be encrypted.
     *
     * @var bool
     */
    public $shouldBeEncrypted = false;

    /**
     * Create a new job instance.
     *
     * @param  \Terablaze\Notifications\NotifiableTrait|\Terablaze\Collection\CollectionInterface  $notifiables
     * @param  \Terablaze\Notifications\Notification  $notification
     * @param  array|null  $channels
     * @return void
     */
    public function __construct($notifiables, $notification, array $channels = null)
    {
        $this->channels = $channels;
        $this->notification = $notification;
        $this->notifiables = $this->wrapNotifiables($notifiables);
        $this->tries = property_exists($notification, 'tries') ? $notification->tries : null;
        $this->timeout = property_exists($notification, 'timeout') ? $notification->timeout : null;
        $this->maxExceptions = property_exists($notification, 'maxExceptions') ? $notification->maxExceptions : null;
        $this->afterCommit = property_exists($notification, 'afterCommit') ? $notification->afterCommit : null;
        $this->shouldBeEncrypted = $notification instanceof ShouldBeEncrypted;
    }

    /**
     * Wrap the notifiable(s) in a collection.
     *
     * @param  \Terablaze\Notifications\NotifiableTrait|\Terablaze\Collection\CollectionInterface  $notifiables
     * @return \Terablaze\Collection\CollectionInterface
     */
    protected function wrapNotifiables($notifiables)
    {
        if ($notifiables instanceof CollectionInterface) {
            return $notifiables;
        } elseif ($notifiables instanceof Model) {
            return EntityCollection::wrap($notifiables);
        }

        return ArrayCollection::wrap($notifiables);
    }

    /**
     * Send the notifications.
     *
     * @param  \Terablaze\Notifications\ChannelManager  $manager
     * @return void
     */
    public function handle(ChannelManager $manager)
    {
        $manager->sendNow($this->notifiables, $this->notification, $this->channels);
    }

    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function displayName()
    {
        return get_class($this->notification);
    }

    /**
     * Call the failed method on the notification instance.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function failed($e)
    {
        if (method_exists($this->notification, 'failed')) {
            $this->notification->failed($e);
        }
    }

    /**
     * Get the number of seconds before a released notification will be available.
     *
     * @return mixed
     */
    public function backoff()
    {
        if (! method_exists($this->notification, 'backoff') && ! isset($this->notification->backoff)) {
            return;
        }

        return $this->notification->backoff ?? $this->notification->backoff();
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime|null
     */
    public function retryUntil()
    {
        if (! method_exists($this->notification, 'retryUntil') && ! isset($this->notification->retryUntil)) {
            return;
        }

        return $this->notification->retryUntil ?? $this->notification->retryUntil();
    }

    /**
     * Prepare the instance for cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->notifiables = clone $this->notifiables;
        $this->notification = clone $this->notification;
    }
}

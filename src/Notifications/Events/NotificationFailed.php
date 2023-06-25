<?php

namespace Terablaze\Notifications\Events;

use Terablaze\Bus\Traits\QueueableTrait;
use Terablaze\Queue\SerializesModels;

class NotificationFailed
{
    use QueueableTrait, SerializesModels;

    /**
     * The notifiable entity who received the notification.
     *
     * @var mixed
     */
    public $notifiable;

    /**
     * The notification instance.
     *
     * @var \Terablaze\Notifications\Notification
     */
    public $notification;

    /**
     * The channel name.
     *
     * @var string
     */
    public $channel;

    /**
     * The data needed to process this failure.
     *
     * @var array
     */
    public $data = [];

    /**
     * Create a new event instance.
     *
     * @param  mixed  $notifiable
     * @param  \Terablaze\Notifications\Notification  $notification
     * @param  string  $channel
     * @param  array  $data
     * @return void
     */
    public function __construct($notifiable, $notification, $channel, $data = [])
    {
        $this->data = $data;
        $this->channel = $channel;
        $this->notifiable = $notifiable;
        $this->notification = $notification;
    }
}

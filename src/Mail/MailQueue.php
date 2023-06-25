<?php

namespace Terablaze\Mail;

interface MailQueue
{
    /**
     * Queue a new e-mail message for sending.
     *
     * @param  \Terablaze\Mail\Mailable|string|array  $view
     * @param  string|null  $queue
     * @return mixed
     */
    public function queue($view, $queue = null);

    /**
     * Queue a new e-mail message for sending after (n) seconds.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  \Terablaze\Mail\MailableInterface|string|array  $view
     * @param  string|null  $queue
     * @return mixed
     */
    public function later($delay, $view, $queue = null);
}

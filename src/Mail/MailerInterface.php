<?php

namespace Terablaze\Mail;

interface MailerInterface
{
    /**
     * Begin the process of mailing a mailable class instance.
     *
     * @param  mixed  $users
     * @return \Terablaze\Mail\PendingMail
     */
    public function to($users);

    /**
     * Begin the process of mailing a mailable class instance.
     *
     * @param  mixed  $users
     * @return \Terablaze\Mail\PendingMail
     */
    public function bcc($users);

    /**
     * Send a new message with only a raw text part.
     *
     * @param  string  $text
     * @param  mixed  $callback
     * @return \Terablaze\Mail\SentMessage|null
     */
    public function raw($text, $callback);

    /**
     * Send a new message using a view.
     *
     * @param  \Terablaze\Mail\MailableInterface|string|array  $view
     * @param  array  $data
     * @param  \Closure|string|null  $callback
     * @return \Terablaze\Mail\SentMessage|null
     */
    public function send($view, array $data = [], $callback = null);
}

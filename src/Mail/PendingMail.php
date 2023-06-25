<?php

namespace Terablaze\Mail;

use Terablaze\Support\Traits\Conditionable;
use Terablaze\Translation\HasLocalePreference;

class PendingMail
{
    use Conditionable;

    /**
     * The mailer instance.
     *
     * @var \Terablaze\Mail\MailerInterface
     */
    protected $mailer;

    /**
     * The locale of the message.
     *
     * @var string
     */
    protected $locale;

    /**
     * The "to" recipients of the message.
     *
     * @var array
     */
    protected $to = [];

    /**
     * The "cc" recipients of the message.
     *
     * @var array
     */
    protected $cc = [];

    /**
     * The "bcc" recipients of the message.
     *
     * @var array
     */
    protected $bcc = [];

    /**
     * Create a new mailable mailer instance.
     *
     * @param  \Terablaze\Mail\MailerInterface  $mailer
     * @return void
     */
    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Set the locale of the message.
     *
     * @param  string  $locale
     * @return $this
     */
    public function locale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Set the recipients of the message.
     *
     * @param  mixed  $users
     * @return $this
     */
    public function to($users)
    {
        $this->to = $users;

        if (! $this->locale && $users instanceof HasLocalePreference) {
            $this->locale($users->preferredLocale());
        }

        return $this;
    }

    /**
     * Set the recipients of the message.
     *
     * @param  mixed  $users
     * @return $this
     */
    public function cc($users)
    {
        $this->cc = $users;

        return $this;
    }

    /**
     * Set the recipients of the message.
     *
     * @param  mixed  $users
     * @return $this
     */
    public function bcc($users)
    {
        $this->bcc = $users;

        return $this;
    }

    /**
     * Send a new mailable message instance.
     *
     * @param  \Terablaze\Mail\MailableInterface  $mailable
     * @return \Terablaze\Mail\SentMessage|null
     */
    public function send(MailableInterface $mailable)
    {
        return $this->mailer->send($this->fill($mailable));
    }

    /**
     * Push the given mailable onto the queue.
     *
     * @param  \Terablaze\Mail\MailableInterface  $mailable
     * @return mixed
     */
    public function queue(MailableInterface $mailable)
    {
        return $this->mailer->queue($this->fill($mailable));
    }

    /**
     * Deliver the queued message after (n) seconds.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  \Terablaze\Mail\MailableInterface  $mailable
     * @return mixed
     */
    public function later($delay, MailableInterface $mailable)
    {
        return $this->mailer->later($delay, $this->fill($mailable));
    }

    /**
     * Populate the mailable with the addresses.
     *
     * @param  \Terablaze\Mail\MailableInterface  $mailable
     * @return \Terablaze\Mail\Mailable
     */
    protected function fill(MailableInterface $mailable)
    {
        return tap($mailable->to($this->to)
            ->cc($this->cc)
            ->bcc($this->bcc), function (MailableInterface $mailable) {
                if ($this->locale) {
                    $mailable->locale($this->locale);
                }
            });
    }
}

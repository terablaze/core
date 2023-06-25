<?php

namespace Terablaze\Notifications\Channels;

use Terablaze\Mail\MailManagerInterface;
use Terablaze\Mail\Mailable;
use Terablaze\Queue\ShouldQueue;
use Terablaze\Mail\Markdown;
use Terablaze\Notifications\Notification;
use Terablaze\Support\ArrayMethods;
use Terablaze\Support\Helpers;
use Terablaze\Support\StringMethods;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;

class MailChannel
{
    /**
     * The mailer implementation.
     *
     * @var \Terablaze\Mail\MailManagerInterface
     */
    protected $mailer;

    /**
     * The markdown implementation.
     *
     * @var \Terablaze\Mail\Markdown
     */
    protected $markdown;

    /**
     * Create a new mail channel instance.
     *
     * @param  \Terablaze\Mail\MailManagerInterface  $mailer
     * @param  \Terablaze\Mail\Markdown  $markdown
     * @return void
     */
    public function __construct(MailManagerInterface $mailer, Markdown $markdown)
    {
        $this->mailer = $mailer;
        $this->markdown = $markdown;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Terablaze\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toMail($notifiable);

        if (! $notifiable->routeNotificationFor('mail', $notification) &&
            ! $message instanceof Mailable) {
            return;
        }

        if ($message instanceof Mailable) {
            return $message->send($this->mailer);
        }

        $this->mailer->mailer($message->mailer ?? null)->send(
            $this->buildView($message),
            array_merge($message->data(), $this->additionalMessageData($notification)),
            $this->messageBuilder($notifiable, $notification, $message)
        );
    }

    /**
     * Get the mailer Closure for the message.
     *
     * @param  mixed  $notifiable
     * @param  \Terablaze\Notifications\Notification  $notification
     * @param  \Terablaze\Notifications\Messages\MailMessage  $message
     * @return \Closure
     */
    protected function messageBuilder($notifiable, $notification, $message)
    {
        return function ($mailMessage) use ($notifiable, $notification, $message) {
            $this->buildMessage($mailMessage, $notifiable, $notification, $message);
        };
    }

    /**
     * Build the notification's view.
     *
     * @param  \Terablaze\Notifications\Messages\MailMessage  $message
     * @return string|array
     */
    protected function buildView($message)
    {
        if ($message->view) {
            return $message->view;
        }

        if (property_exists($message, 'theme') && ! is_null($message->theme)) {
            $this->markdown->theme($message->theme);
        }

        return [
            'html' => $this->markdown->render($message->markdown, $message->data()),
            'text' => $this->markdown->renderText($message->markdown, $message->data()),
        ];
    }

    /**
     * Get additional meta-data to pass along with the view data.
     *
     * @param  \Terablaze\Notifications\Notification  $notification
     * @return array
     */
    protected function additionalMessageData($notification)
    {
        return [
            '__terablaze_notification_id' => $notification->id,
            '__terablaze_notification' => get_class($notification),
            '__terablaze_notification_queued' => in_array(
                ShouldQueue::class,
                class_implements($notification)
            ),
        ];
    }

    /**
     * Build the mail message.
     *
     * @param  \Terablaze\Mail\Message  $mailMessage
     * @param  mixed  $notifiable
     * @param  \Terablaze\Notifications\Notification  $notification
     * @param  \Terablaze\Notifications\Messages\MailMessage  $message
     * @return void
     */
    protected function buildMessage($mailMessage, $notifiable, $notification, $message)
    {
        $this->addressMessage($mailMessage, $notifiable, $notification, $message);

        $mailMessage->subject($message->subject ?: StringMethods::title(
            StringMethods::snake(Helpers::classBasename($notification), ' ')
        ));

        $this->addAttachments($mailMessage, $message);

        if (! is_null($message->priority)) {
            $mailMessage->priority($message->priority);
        }

        if ($message->tags) {
            foreach ($message->tags as $tag) {
                $mailMessage->getHeaders()->add(new TagHeader($tag));
            }
        }

        if ($message->metadata) {
            foreach ($message->metadata as $key => $value) {
                $mailMessage->getHeaders()->add(new MetadataHeader($key, $value));
            }
        }

        $this->runCallbacks($mailMessage, $message);
    }

    /**
     * Address the mail message.
     *
     * @param  \Terablaze\Mail\Message  $mailMessage
     * @param  mixed  $notifiable
     * @param  \Terablaze\Notifications\Notification  $notification
     * @param  \Terablaze\Notifications\Messages\MailMessage  $message
     * @return void
     */
    protected function addressMessage($mailMessage, $notifiable, $notification, $message)
    {
        $this->addSender($mailMessage, $message);

        $mailMessage->to($this->getRecipients($notifiable, $notification, $message));

        if (! empty($message->cc)) {
            foreach ($message->cc as $cc) {
                $mailMessage->cc($cc[0], ArrayMethods::get($cc, 1));
            }
        }

        if (! empty($message->bcc)) {
            foreach ($message->bcc as $bcc) {
                $mailMessage->bcc($bcc[0], ArrayMethods::get($bcc, 1));
            }
        }
    }

    /**
     * Add the "from" and "reply to" addresses to the message.
     *
     * @param  \Terablaze\Mail\Message  $mailMessage
     * @param  \Terablaze\Notifications\Messages\MailMessage  $message
     * @return void
     */
    protected function addSender($mailMessage, $message)
    {
        if (! empty($message->from)) {
            $mailMessage->from($message->from[0], ArrayMethods::get($message->from, 1));
        }

        if (! empty($message->replyTo)) {
            foreach ($message->replyTo as $replyTo) {
                $mailMessage->replyTo($replyTo[0], ArrayMethods::get($replyTo, 1));
            }
        }
    }

    /**
     * Get the recipients of the given message.
     *
     * @param  mixed  $notifiable
     * @param  \Terablaze\Notifications\Notification  $notification
     * @param  \Terablaze\Notifications\Messages\MailMessage  $message
     * @return mixed
     */
    protected function getRecipients($notifiable, $notification, $message)
    {
        if (is_string($recipients = $notifiable->routeNotificationFor('mail', $notification))) {
            $recipients = [$recipients];
        }

        return collect($recipients)->mapWithKeys(function ($recipient, $email) {
            return is_numeric($email)
                    ? [$email => (is_string($recipient) ? $recipient : $recipient->email)]
                    : [$email => $recipient];
        })->all();
    }

    /**
     * Add the attachments to the message.
     *
     * @param  \Terablaze\Mail\Message  $mailMessage
     * @param  \Terablaze\Notifications\Messages\MailMessage  $message
     * @return void
     */
    protected function addAttachments($mailMessage, $message)
    {
        foreach ($message->attachments as $attachment) {
            $mailMessage->attach($attachment['file'], $attachment['options']);
        }

        foreach ($message->rawAttachments as $attachment) {
            $mailMessage->attachData($attachment['data'], $attachment['name'], $attachment['options']);
        }
    }

    /**
     * Run the callbacks for the message.
     *
     * @param  \Terablaze\Mail\Message  $mailMessage
     * @param  \Terablaze\Notifications\Messages\MailMessage  $message
     * @return $this
     */
    protected function runCallbacks($mailMessage, $message)
    {
        foreach ($message->callbacks as $callback) {
            $callback($mailMessage->getSymfonyMessage());
        }

        return $this;
    }
}

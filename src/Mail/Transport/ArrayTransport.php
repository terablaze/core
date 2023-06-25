<?php

namespace Terablaze\Mail\Transport;

use Terablaze\Collection\ArrayCollection;
use Terablaze\Collection\CollectionInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class ArrayTransport implements TransportInterface
{
    /**
     * The collection of Symfony Messages.
     *
     * @var \Terablaze\Collection\CollectionInterface
     */
    protected $messages;

    /**
     * Create a new array transport instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->messages = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        return $this->messages[] = new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    /**
     * Retrieve the collection of messages.
     *
     * @return \Terablaze\Collection\ArrayCollection
     */
    public function messages()
    {
        return $this->messages;
    }

    /**
     * Clear all of the messages from the local collection.
     *
     * @return \Terablaze\Collection\ArrayCollection
     */
    public function flush()
    {
        return $this->messages = new ArrayCollection();
    }

    /**
     * Get the string representation of the transport.
     *
     * @return string
     */
    public function __toString(): string
    {
        return 'array';
    }
}

<?php

namespace TeraBlaze\Log\Events;

use TeraBlaze\EventDispatcher\Event;

class MessageLogged extends Event
{
    /**
     * The log "level".
     *
     * @var string
     */
    public $level;

    /**
     * The log message.
     *
     * @var string
     */
    public $message;

    /**
     * The log context.
     *
     * @var array
     */
    public $context;

    /**
     * Create a new event instance.
     *
     * @param  string  $level
     * @param  string  $message
     * @param  array<int|string, mixed>  $context
     * @return void
     */
    public function __construct($level, $message, array $context = [])
    {
        $this->level = $level;
        $this->message = $message;
        $this->context = $context;
    }

    /**
     * @param string $level
     * @return MessageLogged
     */
    public function setLevel(string $level): MessageLogged
    {
        $this->level = $level;
        return $this;
    }

    /**
     * @return string
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * @param string $message
     * @return MessageLogged
     */
    public function setMessage(string $message): MessageLogged
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param array $context
     * @return MessageLogged
     */
    public function setContext(array $context): MessageLogged
    {
        $this->context = $context;
        return $this;
    }

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

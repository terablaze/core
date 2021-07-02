<?php

namespace TeraBlaze\Ripana\Events;

use TeraBlaze\EventDispatcher\Event;

class PostInitializeEvent extends Event
{
    private string $confKey;

    /**
     * @var array<string, mixed> $conf
     */
    private array $conf;

    /**
     * PreInitializeEvent constructor.
     *
     * @param string $confKey
     * @param array<string, mixed> $conf
     */
    public function __construct(string $confKey, array $conf)
    {
        $this->confKey = $confKey;
        $this->conf = $conf;
    }
}

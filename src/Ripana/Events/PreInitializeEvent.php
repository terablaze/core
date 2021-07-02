<?php

namespace TeraBlaze\Ripana\Events;

use TeraBlaze\EventDispatcher\Event;

class PreInitializeEvent extends Event
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

    /**
     * @param string $confKey
     * @return PreInitializeEvent
     */
    public function setConfKey(string $confKey): PreInitializeEvent
    {
        $this->confKey = $confKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getConfKey(): string
    {
        return $this->confKey;
    }

    /**
     * @param array<string, mixed> $conf
     * @return PreInitializeEvent
     */
    public function setConf(array $conf): PreInitializeEvent
    {
        $this->conf = $conf;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConf(): array
    {
        return $this->conf;
    }
}

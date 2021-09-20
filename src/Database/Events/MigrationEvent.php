<?php

namespace TeraBlaze\Database\Events;

use TeraBlaze\Database\Migrations\Migration;
use TeraBlaze\EventDispatcher\Event;

abstract class MigrationEvent extends Event
{
    /**
     * An migration instance.
     *
     * @var Migration
     */
    public $migration;

    /**
     * The migration method that was called.
     *
     * @var string
     */
    public $method;

    /**
     * Create a new event instance.
     *
     * @param  Migration  $migration
     * @param  string  $method
     * @return void
     */
    public function __construct(Migration $migration, $method)
    {
        $this->method = $method;
        $this->migration = $migration;
    }
}

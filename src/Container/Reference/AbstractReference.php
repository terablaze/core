<?php

namespace Terablaze\Container\Reference;

abstract class AbstractReference
{
    /** @var string $name */
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

<?php

namespace TeraBlaze\Ripana\Database\Migration\Field;

abstract class Field
{
    public string $name;
    public bool $nullable = false;
    public bool $alter = false;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    public function alter(): self
    {
        $this->alter = true;
        return $this;
    }
}

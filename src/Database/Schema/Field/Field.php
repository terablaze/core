<?php

namespace TeraBlaze\Database\Schema\Field;

abstract class Field
{
    public string $name;
    public bool $nullable = false;
    public bool $alter = false;
    public $index = null;
    public $unique = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function alter(): self
    {
        $this->alter = true;
        return $this;
    }

    public function index(?string $name = null): self
    {
        $this->index = $name ?? "index_$this->name";
        return $this;
    }

    public function unique(?string $name = null): self
    {
        $this->unique = $name ?? "unique_$this->name";
        return $this;
    }
}

<?php

namespace TeraBlaze\Database\Schema\Field;

class IntField extends Field
{
    public ?int $default = null;
    public string $type = 'int';
    public int $length = 11;

    public function default(int $value): self
    {
        $this->default = $value;
        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function length(int $length): self
    {
        $this->length = $length;
        return $this;
    }
}

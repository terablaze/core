<?php

namespace TeraBlaze\Ripana\Database\Migration\Field;

class IntField extends Field
{
    public ?int $default = null;

    public function default(int $value): self
    {
        $this->default = $value;
        return $this;
    }
}

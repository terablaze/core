<?php

namespace TeraBlaze\Ripana\Database\Migration\Field;

class FloatField extends Field
{
    public ?float $default = null;

    public function default(float $value): self
    {
        $this->default = $value;
        return $this;
    }
}

<?php

namespace TeraBlaze\Ripana\Database\Migration\Field;

class BoolField extends Field
{
    public ?bool $default = null;

    public function default(bool $value): self
    {
        $this->default = $value;
        return $this;
    }
}

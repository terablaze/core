<?php

namespace TeraBlaze\Ripana\Database\Migration\Field;

class StringField extends Field
{
    public ?string $default = null;

    public function default(string $value): self
    {
        $this->default = $value;
        return $this;
    }
}

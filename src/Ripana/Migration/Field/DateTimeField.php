<?php

namespace TeraBlaze\Ripana\Migration\Field;

class DateTimeField extends Field
{
    public ?string $default = null;

    public function default(string $value): self
    {
        $this->default = $value;
        return $this;
    }
}

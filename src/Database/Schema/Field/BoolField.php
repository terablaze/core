<?php

namespace TeraBlaze\Database\Schema\Field;

class BoolField extends Field
{
    public function default($value): self
    {
        $this->default = (bool) $value;
        return $this;
    }
}

<?php

namespace Terablaze\Database\Schema\Field;

class FloatField extends Field
{
    public bool $unsigned = false;

    public function __construct(string $column)
    {
        parent::__construct($column);
    }

    public function unsigned()
    {
        $this->unsigned = true;
    }
}

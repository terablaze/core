<?php

namespace TeraBlaze\Database\Schema\Field;

class DecimalField extends Field
{
    public int $precision;

    public int $scale;

    public bool $unsigned = false;

    public function __construct(string $column, int $precision = 10, int $scale = 2)
    {
        parent::__construct($column);
        $this->precision = $precision;
        $this->scale = $scale;
    }

    public function unsigned()
    {
        $this->unsigned = true;
    }
}

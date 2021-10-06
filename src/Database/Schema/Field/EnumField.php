<?php

namespace TeraBlaze\Database\Schema\Field;

class EnumField extends Field
{
    public array $enumValues = [];

    /**
     * EnumField constructor.
     * @param string $column
     * @param string[] $enumValues
     */
    public function __construct(string $column, array $enumValues)
    {
        parent::__construct($column);
        $this->enumValues = $enumValues;
    }
}

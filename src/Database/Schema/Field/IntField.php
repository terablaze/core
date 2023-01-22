<?php

namespace Terablaze\Database\Schema\Field;

class IntField extends Field
{
    public $zeroFill = false;

    public bool $autoIncrement = false;

    public bool $unsigned = false;

    public function zeroFill()
    {
        $this->zeroFill = true;
        return $this;
    }

    public function autoIncrement()
    {
        $this->autoIncrement = true;
        return $this;
    }

    public function unsigned()
    {
        $this->unsigned = true;
        return $this;
    }
}

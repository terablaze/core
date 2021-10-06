<?php

namespace TeraBlaze\Database\Schema\Field;

use TeraBlaze\Database\Exception\MigrationException;

class IdField extends Field
{
    public $zeroFill = false;

    public function zeroFill()
    {
        $this->zeroFill = true;
        return $this;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function default($value)
    {
        throw new MigrationException('ID fields cannot have a default value');
    }
    /**
     * @param bool $nullable
     * @return $this
     */
    public function nullable()
    {
        throw new MigrationException('ID fields cannot be null');
    }
}

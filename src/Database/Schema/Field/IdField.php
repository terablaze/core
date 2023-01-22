<?php

namespace Terablaze\Database\Schema\Field;

use Terablaze\Database\Exception\MigrationException;

class IdField extends Field
{
    public bool $zeroFill = false;

    public bool $noAutoIncrement = false;

    public function __construct(string $column, string $type = "", ?int $length = null)
    {
        switch ($type) {
            case 'INT':
            case 'INTEGER':
                $length = $length ?? 11;
                break;
            case 'BIGINT':
                $length = $length ?? 21;
                break;
        }
        parent::__construct($column, $type, $length);
    }

    public function zeroFill()
    {
        $this->zeroFill = true;
        return $this;
    }

    public function noAutoIncrement()
    {
        $this->noAutoIncrement = true;
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
     * @return $this
     */
    public function nullable()
    {
        throw new MigrationException('ID fields cannot be null');
    }
}

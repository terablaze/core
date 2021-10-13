<?php

namespace TeraBlaze\Database\Schema;

use TeraBlaze\Support\ArrayMethods;

class ForeignKey
{
    public $column;
    public ?string $name = null;
    /**
     * @var string|string[]
     */
    public $references;

    public string $referenceTable;
    public string $onDelete;
    public string $onUpdate;

    public function __construct($column, $name = null)
    {
        $this->column = $column;
        $this->name = $name;
    }

    /**
     * @param string|string[] $columns
     * @return $this
     */
    public function references($columns): self
    {
        $this->references = $columns;
        return $this;
    }

    public function on(string $table): self
    {
        $this->referenceTable = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = $action;
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;
        return $this;
    }
}

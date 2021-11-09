<?php

namespace TeraBlaze\Database\Schema\Field;

abstract class Field
{
    public $column;

    public $type = 'string';

    public $length;

    public $default = null;

    public array $index = [];

    public array $unique = [];

    public array $fullText = [];

    public $nullable = false;

    public bool $alter = false;

    public ?string $after = null;

    public ?string $before = null;

    public function __construct(string $column, string $type = "", int $length = 0)
    {
        $this->column = $column;
        $this->type = $type;
        $this->length = $length;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function default($value)
    {
        $this->default = $value;
        return $this;
    }

    /**
     * @return $this
     */
    public function alter()
    {
        $this->alter = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function nullable()
    {
        $this->nullable = true;
        return $this;
    }

    /**
     * @param string|null $name
     * @return $this
     */
    public function index(?string $name = null)
    {
        $this->index[$name ?? "index_" . $this->column] = $this->column;
        return $this;
    }

    /**
     * @param string|null $name
     * @return $this
     */
    public function unique(?string $name = null)
    {
        $this->unique[$name ?? "unique_" . $this->column] = $this->column;
        return $this;
    }

    /**
     * @param string|null $name
     * @return $this
     */
    public function fullText(?string $name = null)
    {
        $this->fullText[$name ?? "fulltext_" . $this->column] = $this->column;
        return $this;
    }

    public function after(string $after)
    {
        $this->after = $after;
        return $this;
    }

    public function before(string $before)
    {
        $this->before = $before;
        return $this;
    }
}

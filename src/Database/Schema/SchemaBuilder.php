<?php

namespace TeraBlaze\Database\Schema;

use TeraBlaze\Database\Schema\Field\BoolField;
use TeraBlaze\Database\Schema\Field\DateTimeField;
use TeraBlaze\Database\Schema\Field\Field;
use TeraBlaze\Database\Schema\Field\FloatField;
use TeraBlaze\Database\Schema\Field\IdField;
use TeraBlaze\Database\Schema\Field\IntField;
use TeraBlaze\Database\Schema\Field\StringField;
use TeraBlaze\Database\Schema\Field\TextField;

abstract class SchemaBuilder implements SchemaBuilderInterface
{
    protected array $fields = [];

    public function bool(string $name): BoolField
    {
        $field = $this->fields[] = new BoolField($name);
        return $field;
    }

    public function dateTime(string $name): DateTimeField
    {
        $field = $this->fields[] = new DateTimeField($name);
        return $field;
    }

    public function float(string $name): FloatField
    {
        $field = $this->fields[] = new FloatField($name);
        return $field;
    }

    public function id(string $name = 'id'): IdField
    {
        $field = $this->fields[] = new IdField($name);
        return $field;
    }

    public function int(string $name): IntField
    {
        $field = $this->fields[] = new IntField($name);
        return $field;
    }

    public function bigInt(string $name): IntField
    {
        return $this->int($name)->type('bigint')->length(20);
    }

    public function string(string $name): StringField
    {
        $field = $this->fields[] = new StringField($name);
        return $field;
    }

    public function text(string $name): TextField
    {
        $field = $this->fields[] = new TextField($name);
        return $field;
    }

    abstract public function execute();
    abstract public function dropColumn(string $name): self;

    public function buildBool(string $prefix, BoolField $field): string
    {
        $template = "{$prefix} `{$field->name}` tinyint(4)";

        if ($field->nullable) {
            $template .= " DEFAULT NULL";
        }

        if ($field->default !== null) {
            $default = (int) $field->default;
            $template .= " DEFAULT {$default}";
        }

        return $template;
    }
}

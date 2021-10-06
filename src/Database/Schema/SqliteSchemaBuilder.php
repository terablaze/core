<?php

namespace TeraBlaze\Database\Schema;

use TeraBlaze\Database\Exception\MigrationException;
use TeraBlaze\Database\Schema\Field\Field;
use TeraBlaze\Database\Schema\Field\BoolField;
use TeraBlaze\Database\Schema\Field\DateTimeField;
use TeraBlaze\Database\Schema\Field\FloatField;
use TeraBlaze\Database\Schema\Field\IdField;
use TeraBlaze\Database\Schema\Field\IntField;
use TeraBlaze\Database\Schema\Field\StringField;
use TeraBlaze\Database\Schema\Field\TextField;

class SqliteSchemaBuilder extends SchemaBuilder
{
    public function build()
    {
        $command = $this->type === 'create' ? '' : 'ALTER TABLE';

        $fields = array_map(fn($field) => $this->stringForField($field), $this->fields);

        if ($this->type === 'create') {
            $fields = join(',' . PHP_EOL, $fields);

            $query = "
                CREATE TABLE \"{$this->table}\" (
                    {$fields}
                );
            ";
        }

        if ($this->type === 'rename') {
            $query = "ALTER TABLE `{$this->table}` RENAME TO `{$this->to}`";
        }

        if ($this->type === 'alter') {
            $fields = join(';' . PHP_EOL, $fields);

            $query = "
                ALTER TABLE \"{$this->table}\"
                {$fields};
            ";
        }

        if ($this->type === 'drop') {
            $query = "DROP TABLE `{$this->table}`;";
        }

        if ($this->type === 'dropIfExists') {
            $query = "DROP TABLE IF EXISTS `{$this->table}`;";
        }

        $this->connection->execute($query);
    }

    private function stringForField(Field $field): string
    {
        $prefix = '';

        if ($this->type === 'alter') {
            $prefix = 'ADD COLUMN';
        }

        if ($field->alter) {
            throw new MigrationException('SQLite doesn\'t support altering columns');
        }

        if ($field instanceof BoolField) {
            return $this->buildBool($prefix, $field);
        }

        if ($field instanceof DateTimeField) {
            $template = "{$prefix} \"{$field->column}\" TEXT";

            if (!$field->nullable) {
                $template .= " NOT NULL";
            }

            if ($field->default === 'CURRENT_TIMESTAMP') {
                $template .= " DEFAULT CURRENT_TIMESTAMP";
            } elseif ($field->default !== null) {
                $template .= " DEFAULT '{$field->default}'";
            }

            return $template;
        }

        if ($field instanceof FloatField) {
            $template = "{$prefix} \"{$field->column}\" REAL";

            if (!$field->nullable) {
                $template .= " NOT NULL";
            }

            if ($field->default !== null) {
                $template .= " DEFAULT {$field->default}";
            }

            return $template;
        }

        if ($field instanceof IdField) {
            return "{$prefix} \"{$field->column}\" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE";
        }

        if ($field instanceof IntField) {
            $template = "{$prefix} \"{$field->column}\" INTEGER";

            if (!$field->nullable) {
                $template .= " NOT NULL";
            }

            if ($field->default !== null) {
                $template .= " DEFAULT {$field->default}";
            }

            return $template;
        }

        if ($field instanceof StringField || $field instanceof TextField) {
            $template = "{$prefix} \"{$field->column}\" TEXT";

            if (!$field->nullable) {
                $template .= " NOT NULL";
            }

            if ($field->default !== null) {
                $template .= " DEFAULT '{$field->default}'";
            }

            return $template;
        }

        throw new MigrationException("Unrecognised field type for {$field->column}");
    }

    public function buildBool(string $prefix, BoolField $field): string
    {
        $template = "{$prefix} \"{$field->column}\" INTEGER";

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $default = (int) $field->default;
            $template .= " DEFAULT {$default}";
        }

        return $template;
    }

    public function dropColumn(string $column = null): self
    {
        throw new MigrationException('SQLite doesn\'t support dropping columns');
    }
}

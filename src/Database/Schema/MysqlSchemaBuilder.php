<?php

namespace TeraBlaze\Database\Schema;

use TeraBlaze\Database\Connection\MysqlConnection;
use TeraBlaze\Database\Exception\MigrationException;
use TeraBlaze\Database\Schema\Field\Field;
use TeraBlaze\Database\Schema\Field\BoolField;
use TeraBlaze\Database\Schema\Field\DateTimeField;
use TeraBlaze\Database\Schema\Field\FloatField;
use TeraBlaze\Database\Schema\Field\IdField;
use TeraBlaze\Database\Schema\Field\IntField;
use TeraBlaze\Database\Schema\Field\StringField;
use TeraBlaze\Database\Schema\Field\TextField;

class MysqlSchemaBuilder extends SchemaBuilder
{
    protected MysqlConnection $connection;
    protected string $table;
    protected string $type;
    protected array $drops = [];

    public function __construct(MysqlConnection $connection, string $table, string $type)
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->type = $type;
    }

    public function  execute()
    {
        $fields = array_map(fn($field) => $this->stringForField($field), $this->fields);

        $primary = array_filter($this->fields, fn($field) => $field instanceof IdField);
        $primaryKey = isset($primary[0]) ? "PRIMARY KEY (`{$primary[0]->name}`)" : '';

        if ($this->type === 'create') {
            $fields = join(PHP_EOL, array_map(fn($field) => "{$field},", $fields));

            $query = "
                CREATE TABLE `{$this->table}` (
                    {$fields}
                    {$primaryKey}
                ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
            ";
        }

        if ($this->type === 'alter') {
            $fields = join(PHP_EOL, array_map(fn($field) => "{$field};", $fields));
            $drops = join(PHP_EOL, array_map(fn($drop) => "DROP COLUMN `{$drop}`;", $this->drops));

            $query = "
                ALTER TABLE `{$this->table}`
                {$fields}
                {$drops}
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
            $prefix = 'ADD';
        }

        if ($field->alter) {
            $prefix = 'MODIFY';
        }

        if ($field instanceof BoolField) {
            return $this->buildBool($prefix, $field);
        }

        if ($field instanceof DateTimeField) {
            $template = "{$prefix} `{$field->name}` datetime";

            if ($field->nullable) {
                $template .= " DEFAULT NULL";
            }

            if ($field->default === 'CURRENT_TIMESTAMP') {
                $template .= " DEFAULT CURRENT_TIMESTAMP";
            } elseif ($field->default !== null) {
                $template .= " DEFAULT '{$field->default}'";
            }

            return $template;
        }

        if ($field instanceof FloatField) {
            $template = "{$prefix} `{$field->name}` float";

            if ($field->nullable) {
                $template .= " DEFAULT NULL";
            }

            if ($field->default !== null) {
                $template .= " DEFAULT '{$field->default}'";
            }

            return $template;
        }

        if ($field instanceof IdField) {
            return "{$prefix} `{$field->name}` int(11) unsigned NOT NULL AUTO_INCREMENT";
        }

        if ($field instanceof IntField) {
            $template = "{$prefix} `{$field->name}` {$field->type}({$field->length})";

            if ($field->nullable) {
                $template .= " DEFAULT NULL";
            }

            if ($field->default !== null) {
                $template .= " DEFAULT '{$field->default}'";
            }

            return $template;
        }

        if ($field instanceof StringField) {
            $template = "{$prefix} `{$field->name}` varchar(255)";

            if ($field->nullable) {
                $template .= " DEFAULT NULL";
            }

            if ($field->default !== null) {
                $template .= " DEFAULT '{$field->default}'";
            }

            return $template;
        }

        if ($field instanceof TextField) {
            return "{$prefix} `{$field->name}` text";
        }

        throw new MigrationException("Unrecognised field type for {$field->name}");
    }

    public function dropColumn(string $name): self
    {
        $this->drops[] = $name;
        return $this;
    }
}
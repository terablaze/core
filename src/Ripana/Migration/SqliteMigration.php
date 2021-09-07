<?php

namespace TeraBlaze\Ripana\Migration;

use TeraBlaze\Ripana\Database\Connection\SqliteConnection;
use TeraBlaze\Ripana\Exception\MigrationException;
use TeraBlaze\Ripana\Migration\Field\Field;
use TeraBlaze\Ripana\Migration\Field\BoolField;
use TeraBlaze\Ripana\Migration\Field\DateTimeField;
use TeraBlaze\Ripana\Migration\Field\FloatField;
use TeraBlaze\Ripana\Migration\Field\IdField;
use TeraBlaze\Ripana\Migration\Field\IntField;
use TeraBlaze\Ripana\Migration\Field\StringField;
use TeraBlaze\Ripana\Migration\Field\TextField;

class SqliteMigration extends Migration
{
    protected SqliteConnection $connection;
    protected string $table;
    protected string $type;

    public function __construct(SqliteConnection $connection, string $table, string $type)
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->type = $type;
    }

    public function execute()
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

        if ($this->type === 'alter') {
            $fields = join(';' . PHP_EOL, $fields);

            $query = "
                ALTER TABLE \"{$this->table}\"
                {$fields};
            ";
        }

        $statement = $this->connection->pdo()->prepare($query);
        $statement->execute();
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
            $template = "{$prefix} \"{$field->name}\" INTEGER";

            if (!$field->nullable) {
                $template .= " NOT NULL";
            }

            if ($field->default !== null) {
                $default = (int) $field->default;
                $template .= " DEFAULT {$default}";
            }

            return $template;
        }

        if ($field instanceof DateTimeField) {
            $template = "{$prefix} \"{$field->name}\" TEXT";

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
            $template = "{$prefix} \"{$field->name}\" REAL";

            if (!$field->nullable) {
                $template .= " NOT NULL";
            }

            if ($field->default !== null) {
                $template .= " DEFAULT {$field->default}";
            }

            return $template;
        }

        if ($field instanceof IdField) {
            return "{$prefix} \"{$field->name}\" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE";
        }

        if ($field instanceof IntField) {
            $template = "{$prefix} \"{$field->name}\" INTEGER";

            if (!$field->nullable) {
                $template .= " NOT NULL";
            }

            if ($field->default !== null) {
                $template .= " DEFAULT {$field->default}";
            }

            return $template;
        }

        if ($field instanceof StringField || $field instanceof TextField) {
            $template = "{$prefix} \"{$field->name}\" TEXT";

            if (!$field->nullable) {
                $template .= " NOT NULL";
            }

            if ($field->default !== null) {
                $template .= " DEFAULT '{$field->default}'";
            }

            return $template;
        }

        throw new MigrationException("Unrecognised field type for {$field->name}");
    }

    public function dropColumn(string $name): self
    {
        throw new MigrationException('SQLite doesn\'t support dropping columns');
    }
}

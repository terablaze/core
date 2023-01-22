<?php

namespace Terablaze\Database\Schema\Builder;

use Terablaze\Database\Exception\MigrationException;
use Terablaze\Database\Schema\Field\DecimalField;
use Terablaze\Database\Schema\Field\EnumField;
use Terablaze\Database\Schema\Field\Field;
use Terablaze\Database\Schema\Field\BoolField;
use Terablaze\Database\Schema\Field\DateTimeField;
use Terablaze\Database\Schema\Field\FloatField;
use Terablaze\Database\Schema\Field\IdField;
use Terablaze\Database\Schema\Field\IntField;
use Terablaze\Database\Schema\Field\JsonField;
use Terablaze\Database\Schema\Field\StringField;
use Terablaze\Database\Schema\Field\TextField;
use Terablaze\Database\Schema\Field\TimeStampField;
use Terablaze\Database\Schema\ForeignKey;
use Terablaze\Support\ArrayMethods;
use Terablaze\Support\StringMethods;

class SqliteBuilder extends AbstractBuilder
{
    public function build(): void
    {
        $query = "";
        $fields = array_map(function (Field $field) {
            $stringForField = $this->stringForField($field);
            if (!is_null($field->after)) {
                return "$stringForField AFTER \"$field->after\"";
            }
            if (!is_null($field->before)) {
                return "$stringForField BEFORE \"$field->before\"";
            }
            return $stringForField;
        }, $this->schema->getFields());

        $primaryKeys = $this->getPrimaryKeys();

        if ($this->schema->getType() === 'create') {
            $fields = join(',' . PHP_EOL, $fields);

            $query = "
                CREATE TABLE \"{$this->schema->getTable()}\" (
                    {$this->cleanQuery($fields . PHP_EOL . $primaryKeys)}
                );
            ";
        }

        if ($this->schema->getType() === 'rename') {
            $query = "ALTER TABLE \"{$this->schema->getTable()}\" RENAME TO \"{$this->schema->getRenameTo()}\"";
        }

        if ($this->schema->getType() === 'alter') {
            $fields = join(';' . PHP_EOL, $fields);
            $drops = $this->compileDrops();
            $renames = $this->compileRenames();

            if (!empty($fields) || !empty($drops) || !empty($renames)) {
                $query = $this->cleanQuery("ALTER TABLE \"{$this->schema->getTable()}\"
                    $renames
                    $drops") . ";";
            }
        }

        if ($this->schema->getType() === 'drop') {
            $query = "DROP TABLE \"{$this->schema->getTable()}\";";
        }

        if ($this->schema->getType() === 'dropIfExists') {
            $query = "DROP TABLE IF EXISTS \"{$this->schema->getTable()}\";";
        }

        if (!empty($query)) {
            $this->schema->getConnection()->execute($query);
        }

        if ($indexes = $this->getIndexes()) {
            $this->schema->getConnection()->execute($indexes);
        }

        if ($foreignKeys = $this->getForeignKeys()) {
            $this->schema->getConnection()->execute("ALTER TABLE \"{$this->schema->getTable()}\" $foreignKeys;");
        }
    }

    protected function getPrimaryKeys(): string
    {
        $query = "";
        if ($this->primaries) {
            $columns = implode('", "', $this->primaries['columns'] ?? []);
            $name = $this->primaries['name'] ?? 'PRIMARY';
            $query = "CONSTRAINT \"$name\" PRIMARY KEY (\"$columns\")";
        }
        return $query;
    }

    protected function stringForField(Field $field): string
    {
        $prefix = '';

        if ($this->schema->getType() === 'alter') {
            $prefix = 'ADD COLUMN';
        }

        if ($field->alter) {
            throw new MigrationException('SQLite doesn\'t support altering columns');
        }

        if ($field instanceof IdField) {
            return $this->buildId($prefix, $field);
        }

        if ($field instanceof IntField) {
            return $this->buildInt($prefix, $field);
        }

        if ($field instanceof BoolField) {
            return $this->buildBool($prefix, $field);
        }

        if ($field instanceof DateTimeField) {
            return $this->buildDateTime($prefix, $field);
        }

        if ($field instanceof FloatField) {
            return $this->buildFloat($prefix, $field);
        }

        if ($field instanceof StringField) {
            return $this->buildString($prefix, $field);
        }

        if ($field instanceof TextField) {
            return $this->buildText($prefix, $field);
        }

        throw new MigrationException("Unrecognised field type for $field->column");
    }

    protected function buildId(string $prefix, IdField $field): string
    {
        $field->length = null;
        $type = $field->type;

        if (in_array(
            StringMethods::upper($type),
            ['INT', 'TINYINT', 'BIGINT', 'INTEGER', 'TINYINTEGER', 'BIGINTEGER']
        )) {
            $type = "INTEGER";
        }

        $template = "$prefix \"$field->column\" $type" . ($field->length ? "($field->length)" : "");

        $template .= " NOT NULL PRIMARY KEY";
        if (!$field->noAutoIncrement) {
            $template .= " AUTOINCREMENT";
        }
        return $template;
    }

    public function buildInt(string $prefix, IntField $field): string
    {
        $template = "$prefix \"$field->column\" INTEGER";

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT $field->default";
        }

        return $template;
    }

    public function buildBool(string $prefix, BoolField $field): string
    {
        $template = "$prefix \"$field->column\" INTEGER";

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $default = (int)$field->default;
            $template .= " DEFAULT $default";
        }

        return $template;
    }

    protected function buildDateTime(string $prefix, DateTimeField $field): string
    {
        $template = "$prefix \"$field->column\" TEXT";

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default === 'CURRENT_TIMESTAMP' || $field->default === 'NOW()') {
            $template .= " DEFAULT CURRENT_TIMESTAMP";
        } elseif ($field->default !== null) {
            $template .= " DEFAULT '$field->default'";
        }

        return $template;
    }

    protected function buildTimeStamp(string $prefix, TimeStampField $field): string
    {
        $template = "$prefix \"$field->column\" INTEGER";

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default === 'CURRENT_TIMESTAMP' || $field->default === 'NOW()') {
            $template .= " DEFAULT CURRENT_TIMESTAMP";
        } elseif ($field->default !== null) {
            $template .= " DEFAULT '$field->default'";
        }

        return $template;
    }

    protected function buildFloat(string $prefix, FloatField $field): string
    {
        $template = "$prefix \"$field->column\" REAL";

        if ($field->unsigned) {
            $template .= " UNSIGNED";
        }

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT $field->default";
        }

        return $template;
    }

    protected function buildDecimal(string $prefix, DecimalField $field): string
    {
        $template = "$prefix \"$field->column\" NUMERIC";

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT $field->default";
        }

        return $template;
    }

    public function buildString(string $prefix, StringField $field): string
    {
        $template = "$prefix \"$field->column\" TEXT";

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '$field->default'";
        }

        return $template;
    }

    public function buildText(string $prefix, TextField $field): string
    {
        $template = "$prefix \"$field->column\" TEXT";

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '$field->default'";
        }

        return $template;
    }

    protected function buildEnum(string $prefix, EnumField $field): string
    {
        $template = "$prefix \"$field->column\" CHECK (\"$field->column\" " .
            "IN(\"" . implode('", "', $field->enumValues) . "\"))";

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '$field->default'";
        }

        return $template;
    }

    protected function buildJson(string $prefix, JsonField $field): string
    {
        $template = "$prefix \"$field->column\" JSON";

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '$field->default'";
        }

        return $template;
    }

    protected function compileRenames(): string
    {
        $columns = implode(
            PHP_EOL,
            array_map(
                function ($from, $to) {
                    return "RENAME COLUMN \"$from\" TO \"$to\",";
                },
                array_keys($this->schema->getRenameColumns()),
                array_values($this->schema->getRenameColumns())
            )
        );
        $indexes = implode(
            PHP_EOL,
            array_map(
                function ($from, $to) {
                    return "RENAME INDEX \"$from\" TO \"$to\",";
                },
                array_keys($this->schema->getRenameIndexes()),
                array_values($this->schema->getRenameIndexes())
            )
        );

        return ($columns ? $columns . PHP_EOL : "") .
            ($indexes ? $indexes . PHP_EOL : "");
    }

    protected function getIndexes(): string
    {
        $indexesString = implode(
            PHP_EOL,
            array_map(
                function ($name, $index) {
                    if (is_array($index)) {
                        $index = implode('", "', $index);
                    }
                    return "CREATE INDEX \"$name\" ON \"{$this->schema->getTable()}\"(\"$index\");";
                },
                array_keys($this->indexes),
                array_values($this->indexes)
            )
        );

        $uniquesString = implode(
            PHP_EOL,
            array_map(
                function ($name, $index) {
                    if (is_array($index)) {
                        $index = implode('", "', $index);
                    }
                    return "CREATE UNIQUE INDEX \"$name\" ON \"{$this->schema->getTable()}\"(\"$index\");";
                },
                array_keys($this->uniques),
                array_values($this->uniques)
            )
        );

        $fullTextString = implode(
            PHP_EOL,
            array_map(
                function ($name, $index) {
                    if (is_array($index)) {
                        $index = implode('", "', $index);
                    }
                    return "CREATE FULLTEXT INDEX \"$name\" ON \"{$this->schema->getTable()}\"(\"$index\");";
                },
                array_keys($this->fullTexts),
                array_values($this->fullTexts)
            )
        );

        return ($indexesString ? $indexesString . PHP_EOL : "") .
            ($uniquesString ? $uniquesString . PHP_EOL : "") .
            ($fullTextString ? $fullTextString . PHP_EOL : "");
    }

    protected function getForeignKeys(): string
    {
        return implode(
            "," . PHP_EOL,
            array_map(
                function (ForeignKey $foreign) {
                    $columns = $foreign->column;
                    $references = $foreign->references;
                    if (is_array($columns)) {
                        $columns = implode('", "', $columns);
                    }
                    if (is_array($references)) {
                        $references = implode('", "', $references);
                    }
                    $foreignKeyName = $foreign->name ??
                        "FK_{$this->schema->getTable()}_" . implode('_', ArrayMethods::wrap($foreign->column));
                    $query = "ADD CONSTRAINT \"$foreignKeyName\" " .
                        "FOREIGN KEY (\"$columns\") REFERENCES \"$foreign->referenceTable\"(\"$references\")";
                    if (isset($foreign->onDelete)) {
                        $query .= " ON DELETE $foreign->onDelete";
                    }
                    if (isset($foreign->onUpdate)) {
                        $query .= " ON UPDATE $foreign->onUpdate";
                    }
                    return $query;
                },
                $this->schema->getForeignKeys()
            )
        );
    }

    protected function compileDrops(): string
    {
        $drops = implode(PHP_EOL, array_map(fn($drop) => "DROP COLUMN \"$drop\",", $this->schema->getDrops()));
        $indexDrops = implode(PHP_EOL, array_map(fn($drop) => "DROP INDEX \"$drop\",", $this->schema->getIndexDrops()));
        $fkDrops = implode(PHP_EOL, array_map(fn($drop) => "DROP FOREIGN KEY \"$drop\",", $this->schema->getFkDrops()));

        return ($drops ? $drops . PHP_EOL : "") .
            ($indexDrops ? $indexDrops . PHP_EOL : "") .
            ($fkDrops ? $fkDrops . PHP_EOL : "");
    }
}

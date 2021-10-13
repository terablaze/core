<?php

namespace TeraBlaze\Database\Schema\Builder;

use TeraBlaze\Database\Exception\MigrationException;
use TeraBlaze\Database\Schema\Field\DecimalField;
use TeraBlaze\Database\Schema\Field\EnumField;
use TeraBlaze\Database\Schema\Field\Field;
use TeraBlaze\Database\Schema\Field\BoolField;
use TeraBlaze\Database\Schema\Field\DateTimeField;
use TeraBlaze\Database\Schema\Field\FloatField;
use TeraBlaze\Database\Schema\Field\IdField;
use TeraBlaze\Database\Schema\Field\IntField;
use TeraBlaze\Database\Schema\Field\JsonField;
use TeraBlaze\Database\Schema\Field\StringField;
use TeraBlaze\Database\Schema\Field\TextField;
use TeraBlaze\Support\StringMethods;

class SqliteBuilder extends AbstractBuilder
{
    public function build(): void
    {
        $query = "";
        $fields = array_map(fn($field) => $this->stringForField($field), $this->schema->getFields());

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

        $template = "$prefix \"$field->column\" $field->type" . ($field->length ? "($field->length)" : "");

        if (StringMethods::endsWith($field->type, "INT")) {
            $template .= " UNSIGNED";
        }

        $template .= " NOT NULL";
        if (!$field->noAutoIncrement) {
            $template .= " AUTO_INCREMENT";
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

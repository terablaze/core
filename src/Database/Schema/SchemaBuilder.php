<?php

namespace TeraBlaze\Database\Schema;

use TeraBlaze\Database\Connection\ConnectionInterface;
use TeraBlaze\Database\Exception\MigrationException;
use TeraBlaze\Database\Schema\Field\BoolField;
use TeraBlaze\Database\Schema\Field\DateTimeField;
use TeraBlaze\Database\Schema\Field\DecimalField;
use TeraBlaze\Database\Schema\Field\EnumField;
use TeraBlaze\Database\Schema\Field\Field;
use TeraBlaze\Database\Schema\Field\FloatField;
use TeraBlaze\Database\Schema\Field\IdField;
use TeraBlaze\Database\Schema\Field\IntField;
use TeraBlaze\Database\Schema\Field\JsonField;
use TeraBlaze\Database\Schema\Field\StringField;
use TeraBlaze\Database\Schema\Field\TextField;
use TeraBlaze\Support\ArrayMethods;

abstract class SchemaBuilder implements SchemaBuilderInterface
{
    public ConnectionInterface $connection;

    protected string $table;
    protected string $type;
    protected ?string $renameTo;

    protected array $fields = [];

    protected array $indexes = [];

    protected array $uniques = [];

    protected array $fullTexts = [];

    protected array $foreignKeys = [];

    protected array $renameColumns = [];
    protected array $renameIndexes = [];

    protected array $drops = [];
    protected array $indexDrops = [];
    protected array $fkDrops = [];

    public function __construct(ConnectionInterface $connection, string $table, string $type)
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->type = $type;
    }

    public function renameTo(string $to)
    {
        $this->renameTo = $to;
        return $this;
    }

    public function id(string $column = 'id', int $length = 11): IdField
    {
        return $this->fields[] = new IdField($column, 'INT', $length);
    }

    public function bigId(string $column = 'id', int $length = 21): IdField
    {
        return $this->fields[] = new IdField($column, 'BIGINT', $length);
    }

    public function tinyInt(string $column, int $length = 4): IntField
    {
        return $this->fields[] = new IntField($column, 'TINYINT', $length);
    }

    public function smallInt(string $column, int $length = 6): IntField
    {
        return $this->fields[] = new IntField($column, 'SMALLINT', $length);
    }

    public function mediumInt(string $column, int $length = 9): IntField
    {
        return $this->fields[] = new IntField($column, 'MEDIUMINT', $length);
    }

    public function int(string $column, int $length = 11): IntField
    {
        return $this->fields[] = new IntField($column, 'INT', $length);
    }

    public function bigInt(string $column, int $length = 21): IntField
    {
        return $this->fields[] = new IntField($column, 'BIGINT', $length);
    }

    public function bool(string $column): BoolField
    {
        return $this->fields[] = new BoolField($column);
    }

    public function dateTime(string $column): DateTimeField
    {
        return $this->fields[] = new DateTimeField($column, "DATETIME");
    }

    public function date(string $column): DateTimeField
    {
        return $this->fields[] = new DateTimeField($column, "DATETIME");
    }

    public function time(string $column): DateTimeField
    {
        return $this->fields[] = new DateTimeField($column, "TIME");
    }

    public function year(string $column): DateTimeField
    {
        return $this->fields[] = new DateTimeField($column, "YEAR");
    }

    public function float(string $column, int $precision = 10, int $scale = 2): FloatField
    {
        return $this->fields[] = new FloatField($column, $precision, $scale);
    }

    public function decimal(string $column, int $precision = 10, int $scale = 2): DecimalField
    {
        return $this->fields[] = new DecimalField($column, $precision, $scale);
    }

    public function string(string $column, int $length = 255): StringField
    {
        return $this->fields[] = new StringField($column, "VARCHAR", $length);
    }

    public function text(string $column): TextField
    {
        return $this->fields[] = new TextField($column, "TEXT");
    }

    public function mediumText(string $column): TextField
    {
        return $this->fields[] = new TextField($column, "MEDIUMTEXT");
    }

    public function longText(string $column): TextField
    {
        return $this->fields[] = new TextField($column, "LONGTEXT");
    }

    public function enum(string $column, array $values): EnumField
    {
        return $this->fields[] = new EnumField($column, $values);
    }

    public function json(string $column): JsonField
    {
        return $this->fields[] = new JsonField($column);
    }

    public function index(array $columns, $name = null): void
    {
        $this->indexes[$name ?? "index_" . implode('_', ArrayMethods::wrap($columns))] = $columns;
    }

    public function unique(array $columns, $name = null): void
    {
        $this->uniques[$name ?? "unique_" . implode('_', ArrayMethods::wrap($columns))] = $columns;
    }

    public function fullText(array $columns, $name = null): void
    {
        $this->fullTexts[$name ?? "fulltext_" . implode('_', ArrayMethods::wrap($columns))] = $columns;
    }

    public function foreign($column, ?string $name = null): ForeignKey
    {
        return $this->foreignKeys[] = new ForeignKey($column, $name);
    }

    public function renameColumn(string $from, string $to): void
    {
        $this->renameColumns[$from] = $to;
    }

    public function renameIndex(string $from, string $to): void
    {
        $this->renameIndexes[$from] = $to;
    }

    public function dropColumn($column = null/*, string ...$columns*/): self
    {
        $columns = is_array($column) ? $column : func_get_args();
        foreach ($columns as $column) {
            $this->drops[] = $column;
        }
        return $this;
    }

    public function dropIndex($index = null/*, string ...$columns*/): self
    {
        $indexes = is_array($index) ? $index : func_get_args();
        foreach ($indexes as $index) {
            $this->indexDrops[] = $index;
        }
        return $this;
    }

    public function dropForeign($key = null/*, string ...$columns*/): self
    {
        $keys = is_array($key) ? $key : func_get_args();
        foreach ($keys as $key) {
            $this->fkDrops[] = $key;
        }
        return $this;
    }

    protected function buildId(string $prefix, IdField $field): string
    {
        $template = "{$prefix} `{$field->column}` {$field->type}({$field->length})";

        if ($field->zeroFill) {
            $template .= " ZEROFILL";
        }

        return $template . " unsigned NOT NULL AUTO_INCREMENT";
    }

    protected function buildInt(string $prefix, IntField $field): string
    {
        $template = "{$prefix} `{$field->column}` {$field->type}({$field->length})";

        if ($field->autoIncrement) {
            $template .= " AUTO_INCREMENT";
        }

        if ($field->zeroFill) {
            $template .= " ZEROFILL";
        }

        if ($field->unsigned) {
            $template .= " UNSIGNED";
        }

        if ($field->nullable) {
            $template .= " DEFAULT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '{$field->default}'";
        }

        return $template;
    }

    protected function buildBool(string $prefix, BoolField $field): string
    {
        $template = "{$prefix} `{$field->column}` tinyint(1)";

        if ($field->nullable) {
            $template .= " DEFAULT NULL";
        }

        if ($field->default !== null) {
            $default = (int) $field->default;
            $template .= " DEFAULT {$default}";
        }

        return $template;
    }

    protected function buildDateTime(string $prefix, DateTimeField $field): string
    {
        $template = "{$prefix} `{$field->column}` $field->type";

        if ($field->nullable) {
            $template .= " DEFAULT NULL";
        }

        if ($field->default === 'CURRENT_TIMESTAMP' || $field->default === 'NOW()') {
            $template .= " DEFAULT $field->default";
        } elseif ($field->default !== null) {
            $template .= " DEFAULT '{$field->default}'";
        }

        return $template;
    }

    protected function buildFloat(string $prefix, FloatField $field): string
    {
        $template = "{$prefix} `{$field->column}` FLOAT($field->precision, $field->scale)";

        if ($field->unsigned) {
            $template .= " UNSIGNED";
        }

        if ($field->nullable) {
            $template .= " DEFAULT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '{$field->default}'";
        }

        return $template;
    }

    protected function buildDecimal(string $prefix, DecimalField $field): string
    {
        $template = "{$prefix} `{$field->column}` DECIMAL($field->precision, $field->scale)";

        if ($field->unsigned) {
            $template .= " UNSIGNED";
        }

        if ($field->nullable) {
            $template .= " DEFAULT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '{$field->default}'";
        }

        return $template;
    }

    protected function buildString(string $prefix, StringField $field): string
    {
        $template = "{$prefix} `{$field->column}` $field->type($field->length)";

        if ($field->nullable) {
            $template .= " DEFAULT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '{$field->default}'";
        }

        return $template;
    }

    protected function buildText(string $prefix, TextField $field): string
    {
        $template = "{$prefix} `{$field->column}` $field->type";

        if ($field->nullable) {
            $template .= " DEFAULT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '{$field->default}'";
        }

        return $template;
    }

    protected function buildEnum(string $prefix, EnumField $field): string
    {
        $template = "{$prefix} `{$field->column}` ENUM(" . implode(', ', $field->enumValues) . ")";

        if ($field->nullable) {
            $template .= " DEFAULT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '{$field->default}'";
        }

        return $template;
    }

    protected function buildJson(string $prefix, JsonField $field): string
    {
        $template = "{$prefix} `{$field->column}` JSON";

        if ($field->nullable) {
            $template .= " DEFAULT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '{$field->default}'";
        }

        return $template;
    }
    
    protected function stringForField(Field $field): string
    {
        $this->getFieldIndexes($field);

        $prefix = '';

        if ($this->type === 'alter') {
            $prefix = 'ADD';
        }

        if ($field->alter) {
            $prefix = 'MODIFY';
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

        if ($field instanceof DecimalField) {
            return $this->buildDecimal($prefix, $field);
        }

        if ($field instanceof StringField) {
            return $this->buildString($prefix, $field);
        }

        if ($field instanceof TextField) {
            return $this->buildText($prefix, $field);
        }

        if ($field instanceof EnumField) {
            return $this->buildEnum($prefix, $field);
        }

        if ($field instanceof JsonField) {
            return $this->buildJson($prefix, $field);
        }

        throw new MigrationException("Unrecognised field type for {$field->column}");
    }

    protected function getFieldIndexes(Field $field)
    {
        if ($field->index) {
            $this->indexes += $field->index;
        }
        if ($field->unique) {
            $this->uniques += $field->unique;
        }
        if ($field->fullText) {
            $this->fullTexts += $field->fullText;
        }
    }

    protected function compileRenames()
    {
        $columns = implode(
            PHP_EOL,
            array_map(
                function($from, $to) {
                    return "RENAME COLUMN `$from` TO `$to`,";
                },
                array_keys($this->renameColumns),
                array_values($this->renameColumns)
            )
        );
        $indexes = implode(
            PHP_EOL,
            array_map(
                function($from, $to) {
                    return "RENAME INDEX `$from` TO `$to`,";
                },
                array_keys($this->renameIndexes),
                array_values($this->renameIndexes)
            )
        );

        return ($columns ? $columns . PHP_EOL : "") .
            ($indexes ? $indexes . PHP_EOL : "");
    }

    protected function compileDrops()
    {
        $drops = implode(PHP_EOL, array_map(fn($drop) => "DROP COLUMN `{$drop}`,", $this->drops));
        $indexDrops = implode(PHP_EOL, array_map(fn($drop) => "DROP INDEX `{$drop}`,", $this->indexDrops));
        $fkDrops = implode(PHP_EOL, array_map(fn($drop) => "DROP FOREIGN KEY `{$drop}`,", $this->fkDrops));

        return ($drops ? $drops . PHP_EOL : "") .
            ($indexDrops ? $indexDrops . PHP_EOL : "") .
            ($fkDrops ? $fkDrops . PHP_EOL : "");
    }

    protected function cleanQuery(string $query)
    {
        return trim($query, ", \t\n\r\0\x0B");
    }
}

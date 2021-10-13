<?php

namespace TeraBlaze\Database\Schema\Builder;

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
use TeraBlaze\Database\Schema\ForeignKey;
use TeraBlaze\Database\Schema\SchemaInterface;
use TeraBlaze\Support\StringMethods;

abstract class AbstractBuilder implements BuilderInterface
{
    protected SchemaInterface $schema;

    /** @var array<string, string|string[]> */
    protected array $primaries = [];

    /** @var string[] */
    protected array $indexes = [];

    /** @var string[] */
    protected array $uniques = [];

    /** @var string[] */
    protected array $fullTexts = [];

    public function __construct(SchemaInterface $schema)
    {
        $this->schema = $schema;
        $this->primaries = $schema->getPrimaries();
        $this->indexes = $schema->getIndexes();
        $this->uniques = $schema->getUniques();
        $this->fullTexts = $schema->getFullTexts();
    }

    protected function buildId(string $prefix, IdField $field): string
    {
        $template = "$prefix `$field->column` $field->type" . ($field->length ? "($field->length)" : "");

        if (StringMethods::endsWith($field->type, "INT")) {
            $template .= " UNSIGNED";
        }
        if ($field->zeroFill) {
            $template .= " ZEROFILL";
        }

        $template .= " NOT NULL";
        if (!$field->noAutoIncrement) {
            $template .= " AUTO_INCREMENT";
        }
        return $template;
    }

    protected function buildInt(string $prefix, IntField $field): string
    {
        $template = "$prefix `$field->column` $field->type($field->length)";

        if ($field->autoIncrement) {
            $template .= " AUTO_INCREMENT";
        }

        if ($field->zeroFill) {
            $template .= " ZEROFILL";
        }

        if ($field->unsigned) {
            $template .= " UNSIGNED";
        }

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '$field->default'";
        }

        return $template;
    }

    protected function buildBool(string $prefix, BoolField $field): string
    {
        $template = "$prefix `$field->column` tinyint(1)";

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
        $template = "$prefix `$field->column` $field->type";

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default === 'CURRENT_TIMESTAMP' || $field->default === 'NOW()') {
            $template .= " DEFAULT $field->default";
        } elseif ($field->useCurrent) {
            $template .= " DEFAULT CURRENT_TIMESTAMP";
        } elseif ($field->default !== null) {
            $template .= " DEFAULT '$field->default'";
        }

        if ($field->useCurrentOnUpdate) {
            $template .= " ON UPDATE CURRENT_TIMESTAMP";
        }

        return $template;
    }

    protected function buildFloat(string $prefix, FloatField $field): string
    {
        $template = "$prefix `$field->column` FLOAT";

        if ($field->unsigned) {
            $template .= " UNSIGNED";
        }

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '$field->default'";
        }

        return $template;
    }

    protected function buildDecimal(string $prefix, DecimalField $field): string
    {
        $template = "$prefix `$field->column` DECIMAL($field->precision, $field->scale)";

        if ($field->unsigned) {
            $template .= " UNSIGNED";
        }

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '$field->default'";
        }

        return $template;
    }

    protected function buildString(string $prefix, StringField $field): string
    {
        $template = "$prefix `$field->column` $field->type($field->length)";

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '$field->default'";
        }

        return $template;
    }

    protected function buildText(string $prefix, TextField $field): string
    {
        $template = "$prefix `$field->column` $field->type";

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
        $template = "$prefix `$field->column` ENUM(\"" . implode('", "', $field->enumValues) . "\")";

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
        $template = "$prefix `$field->column` JSON";

        if (!$field->nullable) {
            $template .= " NOT NULL";
        }

        if ($field->default !== null) {
            $template .= " DEFAULT '$field->default'";
        }

        return $template;
    }

    protected function stringForField(Field $field): string
    {
        $this->getFieldIndexes($field);

        $prefix = '';

        if ($this->schema->getType() === 'alter') {
            $prefix = 'ADD';
        }

        if ($field->alter) {
            $prefix = 'MODIFY';
        }

        if ($field instanceof IdField) {
            $primaries = $this->primaries['columns'] ?? [];
            if ($field->noAutoIncrement) {
                array_push($primaries, $field->column);
            } else {
                array_unshift($primaries, $field->column);
            }
            $this->primaries['columns'] = $primaries;
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

        throw new MigrationException("Unrecognised field type for $field->column");
    }

    protected function getFieldIndexes(Field $field): void
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

    protected function compileRenames(): string
    {
        $columns = implode(
            PHP_EOL,
            array_map(
                function ($from, $to) {
                    return "RENAME COLUMN `$from` TO `$to`,";
                },
                array_keys($this->schema->getRenameColumns()),
                array_values($this->schema->getRenameColumns())
            )
        );
        $indexes = implode(
            PHP_EOL,
            array_map(
                function ($from, $to) {
                    return "RENAME INDEX `$from` TO `$to`,";
                },
                array_keys($this->schema->getRenameIndexes()),
                array_values($this->schema->getRenameIndexes())
            )
        );

        return ($columns ? $columns . PHP_EOL : "") .
            ($indexes ? $indexes . PHP_EOL : "");
    }

    protected function getPrimaryKeys(): string
    {
        $query = "";
        if ($this->primaries) {
            $columns = implode('`, `', $this->primaries['columns'] ?? []);
            $name = $this->primaries['name'] ?? 'PRIMARY';
            $query = "CONSTRAINT `$name` PRIMARY KEY (`$columns`)";
        }
        return $query;
    }

    protected function getIndexes(): string
    {
        $indexesString = implode(
            PHP_EOL,
            array_map(
                function ($name, $index) {
                    if (is_array($index)) {
                        $index = implode('`, `', $index);
                    }
                    return "CREATE INDEX `$name` ON `{$this->schema->getTable()}`(`$index`);";
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
                        $index = implode('`, `', $index);
                    }
                    return "CREATE UNIQUE INDEX `$name` ON `{$this->schema->getTable()}`(`$index`);";
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
                        $index = implode('`, `', $index);
                    }
                    return "CREATE FULLTEXT INDEX `$name` ON `{$this->schema->getTable()}`(`$index`);";
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
                        $columns = implode('`, `', $columns);
                    }
                    if (is_array($references)) {
                        $references = implode('`, `', $references);
                    }
                    $query = "ADD CONSTRAINT `$foreign->name` " .
                        "FOREIGN KEY (`$columns`) REFERENCES `$foreign->referenceTable`(`$references`)";
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
        $drops = implode(PHP_EOL, array_map(fn($drop) => "DROP COLUMN `$drop`,", $this->schema->getDrops()));
        $indexDrops = implode(PHP_EOL, array_map(fn($drop) => "DROP INDEX `$drop`,", $this->schema->getIndexDrops()));
        $fkDrops = implode(PHP_EOL, array_map(fn($drop) => "DROP FOREIGN KEY `$drop`,", $this->schema->getFkDrops()));

        return ($drops ? $drops . PHP_EOL : "") .
            ($indexDrops ? $indexDrops . PHP_EOL : "") .
            ($fkDrops ? $fkDrops . PHP_EOL : "");
    }

    protected function cleanQuery(string $query): string
    {
        return trim($query, ", \t\n\r\0\x0B");
    }
}

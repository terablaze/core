<?php

namespace TeraBlaze\Database\Schema;

use TeraBlaze\Database\Connection\MysqlConnection;
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
use TeraBlaze\Support\ArrayMethods;

class MysqlSchemaBuilder extends SchemaBuilder
{
    public function build()
    {
        $fields = array_map(fn($field) => $this->stringForField($field), $this->fields);

        $primaries = array_filter($this->fields, fn($field) => $field instanceof IdField);
        $primaryColumns = array_map(fn(IdField $primary) => $primary->column, $primaries);
        $primary = implode(', ', ArrayMethods::wrap($primaryColumns));
        $primaryKey = !empty($primary) ? "PRIMARY KEY (`{$primary}`)" : '';

        if ($this->type === 'create') {
            $fields = join(PHP_EOL, array_map(fn($field) => "{$field},", $fields));

            $query = "
                CREATE TABLE `{$this->table}` (
                    {$fields}
                    {$primaryKey}
                ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
            ";
        }

        if ($this->type === 'rename') {
            $query = "ALTER TABLE `{$this->table}` RENAME TO `{$this->renameTo}`";
        }

        if ($this->type === 'alter') {
            $fields = join(PHP_EOL, array_map(fn($field) => "{$field},", $fields));
            $drops = $this->compileDrops();
            $renames = $this->compileRenames();

            $query = $this->cleanQuery("ALTER TABLE `{$this->table}`
                {$fields}
                {$renames}
                {$drops}") . ";";
        }

        if ($this->type === 'drop') {
            $query = "DROP TABLE `{$this->table}`;";
        }

        if ($this->type === 'dropIfExists') {
            $query = "DROP TABLE IF EXISTS `{$this->table}`;";
        }

        $this->connection->execute($query);

        if ($indexes = $this->getIndexes()) {
            $this->connection->execute($indexes);
        }

        if ($fks = $this->getForeignKeys()) {
            $this->connection->execute("ALTER TABLE `$this->table` $fks;");
        }
    }

    public function getIndexes()
    {
        $indexesString = implode(
            PHP_EOL,
            array_map(
                function($name, $index) {
                    if (is_array($index)) {
                        $index = implode('`, `', $index);
                    }
                    return "CREATE INDEX `$name` ON `$this->table`(`$index`);";
                },
                array_keys($this->indexes),
                array_values($this->indexes)
            )
        );

        $uniquesString = implode(
            PHP_EOL,
            array_map(
                function($name, $index) {
                    if (is_array($index)) {
                        $index = implode('`, `', $index);
                    }
                    return "CREATE UNIQUE INDEX `$name` ON `$this->table`(`$index`);";
                },
                array_keys($this->uniques),
                array_values($this->uniques)
            )
        );

        $fullTextString = implode(
            PHP_EOL,
            array_map(
                function($name, $index) {
                    if (is_array($index)) {
                        $index = implode('`, `', $index);
                    }
                    return "CREATE FULLTEXT INDEX `$name` ON `$this->table`(`$index`);";
                },
                array_keys($this->fullTexts),
                array_values($this->fullTexts)
            )
        );

        return ($indexesString ? $indexesString . PHP_EOL : "") .
            ($uniquesString ? $uniquesString . PHP_EOL : "") .
            ($fullTextString ? $fullTextString . PHP_EOL : "");
    }

    public function getForeignKeys()
    {
        return implode(
            "," . PHP_EOL,
            array_map(
                function(ForeignKey $foreign) {
                    $columns  = $foreign->column;
                    $references  = $foreign->references;
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
                $this->foreignKeys
            )
        );
    }
}

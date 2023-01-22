<?php

namespace Terablaze\Database\Schema\Builder;

use Terablaze\Database\Exception\MigrationException;
use Terablaze\Database\Schema\Field\Field;

class MysqlBuilder extends AbstractBuilder
{
    public function build(): void
    {
        $query = "";
        $fields = array_map(function (Field $field) {
            $stringForField = $this->stringForField($field);
            if (!is_null($field->after)) {
                return "$stringForField AFTER `$field->after`";
            }
            if (!is_null($field->before)) {
                return "$stringForField BEFORE `$field->before`";
            }
            return $stringForField;
        }, $this->schema->getFields());

        $primaryKeys = $this->getPrimaryKeys();

        if ($this->schema->getType() === 'create') {
            $fields = join(PHP_EOL, array_map(fn($field) => "$field,", $fields));

            $query = "
                CREATE TABLE `{$this->schema->getTable()}` (
                    {$this->cleanQuery($fields . PHP_EOL . $primaryKeys)}
                ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
            ";
        }

        if ($this->schema->getType() === 'rename') {
            $query = "ALTER TABLE `{$this->schema->getTable()}` RENAME TO `{$this->schema->getRenameTo()}`";
        }

        if ($this->schema->getType() === 'alter') {
            $fields = join(PHP_EOL, array_map(fn($field) => "$field,", (array)$fields));
            $drops = $this->compileDrops();
            $renames = $this->compileRenames();

            if (!empty($fields) || !empty($drops) || !empty($renames)) {
                $query = $this->cleanQuery("ALTER TABLE `{$this->schema->getTable()}`
                    $fields
                    $renames
                    $drops") . ";";
            }
        }

        if ($this->schema->getType() === 'drop') {
            $query = "DROP TABLE `{$this->schema->getTable()}`;";
        }

        if ($this->schema->getType() === 'dropIfExists') {
            $query = "DROP TABLE IF EXISTS `{$this->schema->getTable()}`;";
        }

        if (!empty($query)) {
            $this->schema->getConnection()->execute($query);
        }

        if ($indexes = $this->getIndexes()) {
            $this->schema->getConnection()->execute($indexes);
        }

        if ($foreignKeys = $this->getForeignKeys()) {
            $this->schema->getConnection()->execute("ALTER TABLE `{$this->schema->getTable()}` $foreignKeys;");
        }
    }
}

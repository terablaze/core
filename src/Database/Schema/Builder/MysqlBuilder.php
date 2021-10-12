<?php

namespace TeraBlaze\Database\Schema\Builder;

use TeraBlaze\Database\Exception\MigrationException;

class MysqlBuilder extends AbstractBuilder
{
    public function build(): void
    {
        $query = "";
        $fields = array_map(fn($field) => $this->stringForField($field), $this->schema->getFields());

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
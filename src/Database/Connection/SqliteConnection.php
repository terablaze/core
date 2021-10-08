<?php

namespace TeraBlaze\Database\Connection;

use TeraBlaze\Database\Query\SqliteQueryBuilder;
use InvalidArgumentException;
use PDO;
use TeraBlaze\Database\Schema\SqliteSchema;

class SqliteConnection extends Connection implements ConnectionInterface
{
    public function __construct(array $config)
    {
        parent::__construct($config);
        ['path' => $path] = $config;

        if (empty($path)) {
            throw new InvalidArgumentException('Connection incorrectly configured');
        }

        $this->pdo = new PDO("sqlite:{$path}");
    }

    public function query(): SqliteQueryBuilder
    {
        return new SqliteQueryBuilder($this);
    }

    public function createTable(string $table): SqliteSchema
    {
        return new SqliteSchema($this, $table, 'create');
    }

    public function alterTable(string $table): SqliteSchema
    {
        return new SqliteSchema($this, $table, 'alter');
    }

    public function dropTable(string $table): SqliteSchema
    {
        return new SqliteSchema($this, $table, 'drop');
    }

    public function dropTableIfExists(string $table): SqliteSchema
    {
        return new SqliteSchema($this, $table, 'dropIfExists');
    }

    public function renameTable(string $from, string $to): SqliteSchema
    {
        return (new SqliteSchema($this, $from, 'rename'))->renameTo($to);
    }

    public function getTables(): array
    {
        $statement = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table'");
        $statement->execute();

        $results = $statement->fetchAll(PDO::FETCH_NUM);
        $results = array_map(fn($result) => $result[0], $results);

        return $results;
    }

    public function hasTable(string $name): bool
    {
        $tables = $this->getTables();
        return in_array($name, $tables);
    }

    public function dropTables(): int
    {
        file_put_contents($this->config['path'], '');
        return 1;
    }
}

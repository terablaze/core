<?php

namespace TeraBlaze\Ripana\Database\Connection;

use TeraBlaze\Ripana\Database\Migration\SqliteMigration;
use TeraBlaze\Ripana\Database\QueryBuilder\SqliteQueryBuilder;
use InvalidArgumentException;
use PDO;

class SqliteConnection extends Connection implements ConnectionInterface
{
    private array $config;

    public function __construct(array $config)
    {
        parent::__construct();
        ['path' => $path] = $config;

        if (empty($path)) {
            throw new InvalidArgumentException('Connection incorrectly configured');
        }

        $this->pdo = new PDO("sqlite:{$path}");
        $this->config = $config;
    }
    
    public function query(): SqliteQueryBuilder
    {
        return new SqliteQueryBuilder($this);
    }

    public function createTable(string $table): SqliteMigration
    {
        return new SqliteMigration($this, $table, 'create');
    }

    public function alterTable(string $table): SqliteMigration
    {
        return new SqliteMigration($this, $table, 'alter');
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

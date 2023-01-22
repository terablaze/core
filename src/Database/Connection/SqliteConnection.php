<?php

namespace Terablaze\Database\Connection;

use Terablaze\Database\Query\SqliteQueryBuilder;
use InvalidArgumentException;
use PDO;
use Terablaze\Database\Schema\SqliteSchema;

class SqliteConnection extends Connection implements ConnectionInterface
{
    public function connect(): PDO
    {
        $options = $this->getOptions($this->config);

        // SQLite supports "in-memory" databases that only last as long as the owning
        // connection does. These are useful for tests or for short lifetime store
        // querying. In-memory databases may only have a single open connection.
        if ($this->config['database'] === ':memory:') {
            return $this->createConnection('sqlite::memory:', $this->config, $options);
        }

        $path = realpath($this->config['database']);

        // Here we'll verify that the SQLite database exists before going any further
        // as the developer probably wants to know if the database exists and this
        // SQLite driver will not throw any exception if it does not by default.
        if ($path === false) {
            throw new InvalidArgumentException("Database ({$this->config['database']}) does not exist.");
        }

        return $this->createConnection("sqlite:{$path}", $this->config, $options);
    }

    public function query(): SqliteQueryBuilder
    {
        return new SqliteQueryBuilder($this);
    }

    public function getSchema(string $table): SqliteSchema
    {
        return new SqliteSchema($this, $table);
    }

    public function getTables(): array
    {
        $statement = $this->execute("SELECT name FROM sqlite_master WHERE type = 'table'");

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
        file_put_contents($this->config['database'], '');
        return 1;
    }

    /**
     * Compile the command to enable foreign key constraints.
     *
     * @return string
     */
    protected function compileEnableForeignKeyConstraints()
    {
        return 'PRAGMA foreign_keys = ON;';
    }

    /**
     * Compile the command to disable foreign key constraints.
     *
     * @return string
     */
    protected function compileDisableForeignKeyConstraints()
    {
        return 'PRAGMA foreign_keys = OFF;';
    }
}

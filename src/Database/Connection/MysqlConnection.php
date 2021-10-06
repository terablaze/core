<?php

namespace TeraBlaze\Database\Connection;

use TeraBlaze\Database\Schema\MysqlSchemaBuilder;
use TeraBlaze\Database\Query\MysqlQueryBuilder;
use InvalidArgumentException;
use PDO;

class MysqlConnection extends Connection implements ConnectionInterface
{
    private string $database;

    public function __construct(array $config)
    {
        parent::__construct($config);
        [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
        ] = $config;
        $database = $config['database'] ?? $config['schema'];

        if (empty($host) || empty($database) || empty($username)) {
            throw new InvalidArgumentException('Connection incorrectly configured');
        }

        $this->database = $database;
        $this->defaultFetchMode = $config['defaultFetchMode'] ?? PDO::FETCH_ASSOC;

        $this->pdo = new PDO("mysql:host={$host};port={$port};dbname={$database}", $username, $password);
    }

    public function query(): MysqlQueryBuilder
    {
        return new MysqlQueryBuilder($this);
    }

    public function createTable(string $table): MysqlSchemaBuilder
    {
        return new MysqlSchemaBuilder($this, $table, 'create');
    }

    public function alterTable(string $table): MysqlSchemaBuilder
    {
        return new MysqlSchemaBuilder($this, $table, 'alter');
    }

    public function dropTable(string $table): MysqlSchemaBuilder
    {
        return new MysqlSchemaBuilder($this, $table, 'drop');
    }

    public function dropTableIfExists(string $table): MysqlSchemaBuilder
    {
        return new MysqlSchemaBuilder($this, $table, 'dropIfExists');
    }

    public function renameTable(string $from, string $to): MysqlSchemaBuilder
    {
        return (new MysqlSchemaBuilder($this, $from, 'rename'))->renameTo($to);
    }

    public function getTables(): array
    {
        $statement = $this->pdo->prepare('SHOW TABLES');
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
        $statement = $this->pdo->prepare("
            SELECT CONCAT('DROP TABLE IF EXISTS `', table_name, '`')
            FROM information_schema.tables
            WHERE table_schema = '{$this->database}';
        ");

        $statement->execute();

        $dropTableClauses = $statement->fetchAll(PDO::FETCH_NUM);
        $dropTableClauses = array_map(fn($result) => $result[0], $dropTableClauses);

        $clauses = [
            'SET FOREIGN_KEY_CHECKS = 0',
            ...$dropTableClauses,
            'SET FOREIGN_KEY_CHECKS = 1',
        ];

        $statement = $this->pdo->prepare(join(';', $clauses) . ';');

        return $statement->execute();
    }
}

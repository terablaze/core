<?php

namespace TeraBlaze\Ripana\Database\Connectors;

use MySQLi;
use TeraBlaze\Events\Events;
use TeraBlaze\Ripana\Database\Exception\ServiceException;
use TeraBlaze\Ripana\Database\Exception\SqlException;
use TeraBlaze\Ripana\Database\Query\MysqliQuery;
use TeraBlaze\Ripana\Database\Query\QueryInterface;
use TeraBlaze\Ripana\ORM\Model;

class MysqliConnector extends Connector implements ConnectorInterface
{
    public const QUERY_BEFORE_EVENT = "terablaze.ripana.database.connector.mysql.query.before",
        QUERY_AFTER_EVENT = "terablaze.ripana.database.connector.mysql.query.after";

    /**
     * @var mysqli
     */
    protected $_service;

    /**
     * @readwrite
     */
    protected $_host;

    /**
     * @readwrite
     */
    protected $_username;

    /**
     * @readwrite
     */
    protected $_password;

    /**
     * @readwrite
     */
    protected $_schema;

    /**
     * @readwrite
     */
    protected $_port = "3306";

    /**
     * @readwrite
     */
    protected $_charset = "utf8";

    /**
     * @readwrite
     */
    protected $_engine = "InnoDB";

    /**
     * @readwrite
     */
    protected $_isConnected = false;

    protected $dbConfName = 'default';

    public function setDatabaseConfName(string $dbConfName): ConnectorInterface
    {
        $this->dbConfName = $dbConfName;
        return $this;
    }

    public function getDatabaseConfName(): string
    {
        return $this->dbConfName;
    }

    public function connect(): ConnectorInterface
    {
        if (!$this->_isValidService()) {
            $this->_service = new mysqli(
                $this->_host,
                $this->_username,
                $this->_password,
                $this->_schema,
                $this->_port
            );

            if ($this->_service->connect_error) {
                throw new ServiceException("Unable to connect to service");
            }

            $this->isConnected = true;
        }

        return $this;
    }

    /**
     * checks if connected to the database
     */
    protected function _isValidService()
    {
        $isEmpty = empty($this->_service);
        $isInstance = $this->_service instanceof mysqli;

        if ($this->isConnected && $isInstance && !$isEmpty) {
            return true;
        }

        return false;
    }

    public function disconnect(): ConnectorInterface
    {
        if ($this->_isValidService()) {
            $this->isConnected = false;
            $this->_service->close();
        }

        return $this;
    }

    public function query(): QueryInterface
    {
        return new MysqliQuery($this);
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, $dumpSql = '')
    {
        if ($dumpSql) {
            if (function_exists($dumpSql)) {
                $dumpSql($sql);
            } else {
                if (function_exists('dump')) {
                    dump($sql);
                } else {
                    echo $sql;
                }
            }
        }
        Events::fire(self::QUERY_BEFORE_EVENT, array($sql, ""));
        if (!$this->_isValidService()) {
            throw new ServiceException("Not connected to a valid service");
        }
        $this->queryLogger->startLog($sql);
        $result = $this->_service->query($sql);
        $this->queryLogger->stopLog($this->_service->affected_rows);
        Events::fire(self::QUERY_AFTER_EVENT, array($sql, ""));
        return $result;
    }


    public function escape(string $value): string
    {
        if (!$this->_isValidService()) {
            throw new ServiceException("Not connected to a valid service");
        }

        return $this->_service->real_escape_string($value);
    }

    public function getLastInsertId()
    {
        if (!$this->_isValidService()) {
            throw new ServiceException("Not connected to a valid service");
        }

        return $this->_service->insert_id;
    }

    public function getAffectedRows(): int
    {
        if (!$this->_isValidService()) {
            throw new ServiceException("Not connected to a valid service");
        }

        return $this->_service->affected_rows;
    }

    public function getLastError(): string
    {
        if (!$this->_isValidService()) {
            throw new ServiceException("Not connected to a valid service");
        }

        return $this->_service->error;
    }

    public function buildSyncSQL(string $modelClass): array
    {
        $queries = [];
        /** @var Model $model */
        $model = new $modelClass();
        if (!empty($query = $model->retrieveSyncSQL())) {
            $queries = is_array($query) ? $query : [$query];
        } else {
            $lines = [];
            $indices = [];
            $columns = $model->getColumns();
            $template = "CREATE TABLE `%s` (\n%s,\n%s\n) ENGINE=%s DEFAULT CHARSET=%s;";
            foreach ($columns as $column) {
                $raw = $column["raw"];
                $name = $column["name"];
                $type = $column["type"];
                $length = $column["length"];
                $tempDefault = isset($column["default"]) ? $this->escape($column["default"]) : null;
                if (isset($tempDefault)) {
                    if (
                        !in_array(mb_strtolower($tempDefault), ['now()'], true) &&
                        (!in_array(mb_strtolower($type), ["boolean", "bool", "date", "time", "datetime"], true) && !is_numeric($tempDefault))
                    ) {
                        $tempDefault = "'{$tempDefault}'";
                    }
                }
                $default = isset($tempDefault) ? " DEFAULT $tempDefault" : "";
                $nullable = $column["nullable"] ? "" : " NOT NULL";
                $isForeignKey = isset($column['foreignKey']) && $column['foreignKey'] == true;
                if ($column["primary"]) {
                    $indices[] = "PRIMARY KEY (`{$name}`)";
                }
                if ($column["index"]) {
                    switch (strtolower($column["index"])) {
                        case "index":
                            $indices[] = "INDEX `INDEX_{$name}` (`{$name}`)";
                            break;
                        case "uniqueindex":
                        case "unique-index":
                        case "unique_index":
                            $indices[] = "UNIQUE INDEX `UNIQUE_INDEX_{$name}` (`{$name}`)";
                            break;
                        case "key":
                            $indices[] = "KEY `KEY_{$name}` (`{$name}`)";
                            break;
                        case "uniquekey":
                        case "unique-key":
                        case "unique_key":
                            $indices[] = "UNIQUE KEY `UNIQUE_KEY_{$name}` (`{$name}`)";
                            break;
                    }
                }
                if ($isForeignKey) {
                    $queries = array_merge($queries, $this->buildSyncSQL($column['foreignClass']));
                    $indices[] = "CONSTRAINT fk_{$name} FOREIGN KEY ({$name}) REFERENCES {$column['table']}({$column['foreignKeyName']})";
                }
                switch ($type) {
                    case Model::DATA_TYPES['autonumber']:
                    {
                        $length = $length ?? 11;
                        $lines[] = $isForeignKey ? "`{$name}` int({$length})" : "`{$name}` int({$length}) NOT NULL AUTO_INCREMENT";
                        break;
                    }
                    case Model::DATA_TYPES['text']:
                    {
                        if ($length !== null && $length <= 255) {
                            $lines[] = "`{$name}` varchar({$length}){$nullable}{$default}";
                        } else {
                            $lines[] = "`{$name}` text{$nullable}";
                        }
                        break;
                    }
                    case Model::DATA_TYPES['integer']:
                    {
                        $lines[] = "`{$name}` int(11){$nullable}{$default}";
                        break;
                    }
                    case Model::DATA_TYPES['decimal']:
                    {
                        $lines[] = "`{$name}` float{$nullable}{$default}";
                        break;
                    }
                    case Model::DATA_TYPES['boolean']:
                    case Model::DATA_TYPES['bool']:
                    {
                        $lines[] = "`{$name}` tinyint(1){$nullable}{$default}";
                        break;
                    }
                    case Model::DATA_TYPES['datetime']:
                    {
                        $lines[] = "`{$name}` datetime{$nullable}{$default}";
                        break;
                    }
                    default:
                    {
                        $typePart = $length ? " {$type}({$length})" : " {$type}";
                        $lines[] = "`{$name}`{$typePart}{$nullable}{$default}";
                        break;
                    }
                }
            }
            $table = $model->getTable();
            $sql = sprintf(
                $template,
                $table,
                join(",\n", $lines),
                join(",\n", $indices),
                $this->_engine,
                $this->_charset
            );
            $queries[] = "DROP TABLE IF EXISTS `{$table}`;";
            $queries[] = $sql;

            $model->storeSyncSQL($queries);
        }

        return $queries;
    }

    public function sync(string $model): ConnectorInterface
    {
        $queries = $this->buildSyncSQL($model);
        foreach ($queries as $query) {
            $result = $this->execute($query);
            if ($result === false) {
                $error = $this->lastError;
                throw new SqlException("There was an error in the query: {$error} of \n {$query}");
            }
        }
        return $this;
    }
}

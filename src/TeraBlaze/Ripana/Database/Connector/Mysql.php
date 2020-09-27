<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/1/2017
 * Time: 4:47 AM
 */

namespace TeraBlaze\Ripana\Database\Connector;

use TeraBlaze\Ripana\Database as Database;
use TeraBlaze\Ripana\Database\Exception as Exception;
use TeraBlaze\Ripana\Database\Query\Mysql as QueryMysql;
use TeraBlaze\Ripana\Database\Query\Query;
use TeraBlaze\Ripana\ORM\Model;

class Mysql extends Connector
{
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

    /**
     * connects to the database
     */
    public function connect()
    {
        if (!$this->_isValidService()) {
            $this->_service = new \MySQLi(
                $this->_host,
                $this->_username,
                $this->_password,
                $this->_schema,
                $this->_port
            );

            if ($this->_service->connect_error) {
                throw new Exception\Service("Unable to connect to service");
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
        $isInstance = $this->_service instanceof \MySQLi;

        if ($this->isConnected && $isInstance && !$isEmpty) {
            return true;
        }

        return false;
    }

    /**
     * disconnects from the database
     */
    public function disconnect()
    {
        if ($this->_isValidService()) {
            $this->isConnected = false;
            $this->_service->close();
        }

        return $this;
    }

    /**
     * returns a corresponding query instance
     */
    public function query(): Query
    {
        return new QueryMysql(array(
            "connector" => $this
        ));
    }

    /**
     * executes the provided SQL statement
     */
    public function execute(string $sql)
    {
        \TeraBlaze\Events\Events::fire("terablaze.libraries.database.query", array($sql, ""));
        if (!$this->_isValidService()) {
            throw new Exception\Service("Not connected to a valid service");
        }

        return $this->_service->query($sql);
    }

    /**
     * escapes the provided value to make it safe for queries
     */
    public function escape(string $value)
    {
        if (!$this->_isValidService()) {
            throw new Exception\Service("Not connected to a valid service");
        }

        return $this->_service->real_escape_string($value);
    }

    /**
     * returns the ID of the last row
     * to be inserted
     */
    public function getLastInsertId()
    {
        if (!$this->_isValidService()) {
            throw new Exception\Service("Not connected to a valid service");
        }

        return $this->_service->insert_id;
    }

    /**
     * returns the number of rows affected
     * by the last SQL query executed
     */
    public function getAffectedRows()
    {
        if (!$this->_isValidService()) {
            throw new Exception\Service("Not connected to a valid service");
        }

        return $this->_service->affected_rows;
    }

    /**
     * returns the last error that occured
     */
    public function getLastError()
    {
        if (!$this->_isValidService()) {
            throw new Exception\Service("Not connected to a valid service");
        }

        return $this->_service->error;
    }

    public function buildSyncSQL($modelClass)
    {
        $queries = [];
        $model = new $modelClass;
        $lines = [];
        $indices = [];
        $columns = $model->getColumns();
        $template = "CREATE TABLE `%s` (\n%s,\n%s\n) ENGINE=%s DEFAULT CHARSET=%s;";
        foreach ($columns as $column) {
            $raw = $column["raw"];
            $name = $column["name"];
            $type = $column["type"];
            $length = $column["length"];
            $default = $column["default"] ? " DEFAULT '{$this->escape($column["default"])}'" : "";
            $nullable = $column["nullable"] ? "" : " NOT NULL";
            $isForeignKey = isset($column['foreignKey']) && $column['foreignKey'] == true;
            if ($column["primary"]) {
                $indices[] = "PRIMARY KEY (`{$name}`)";
            }
            if ($column["index"]) {
                $indices[] = "KEY `{$name}` (`{$name}`)";
            }
            if ($isForeignKey) {
                $queries = array_merge($queries, $this->buildSyncSQL($column['foreignClass']));
                $indices[] = "CONSTRAINT fk_{$name} FOREIGN KEY ({$name}) REFERENCES {$column['table']}({$column['foreignKeyName']})";
            }
            switch ($type) {
                case Model::DATA_TYPES['autonumber']: {
                        $length = $length ?? 11;
                        $lines[] = $isForeignKey ? "`{$name}` int({$length})" : "`{$name}` int({$length}) NOT NULL AUTO_INCREMENT";
                        break;
                    }
                case Model::DATA_TYPES['text']: {
                        if ($length !== null && $length <= 255) {
                            $lines[] = "`{$name}` varchar({$length}){$nullable}{$default}";
                        } else {
                            $lines[] = "`{$name}` text{$nullable}";
                        }
                        break;
                    }
                case Model::DATA_TYPES['integer']: {
                        $lines[] = "`{$name}` int(11){$nullable}{$default}";
                        break;
                    }
                case Model::DATA_TYPES['decimal']: {
                        $lines[] = "`{$name}` float{$nullable}{$default}";
                        break;
                    }
                case Model::DATA_TYPES['boolean']: {
                        $lines[] = "`{$name}` tinyint(4){$nullable}{$default}";
                        break;
                    }
                case Model::DATA_TYPES['datetime']: {
                        $lines[] = "`{$name}` datetime{$nullable}{$default}";
                        break;
                    }
                default: {
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

        return $queries;
    }

    public function sync($model): self
    {
        $queries = $this->buildSyncSQL($model);
        foreach ($queries as $query) {
            $result = $this->execute($query);
            if ($result === false) {
                $error = $this->lastError;
                throw new Exception\Sql("There was an error in the query: {$error} of \n {$query}");
            }
        }
        return $this;
    }
}

<?php

namespace TeraBlaze\Database;

use ReflectionException;
use TeraBlaze\Config\Exception\InvalidContextException;
use TeraBlaze\Container\Exception\ServiceNotFoundException;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Database\Connection\ConnectionInterface;
use TeraBlaze\Database\Connection\MysqlConnection;
use TeraBlaze\Database\Connection\SqliteConnection;
use TeraBlaze\Database\Legacy\Connectors\ConnectorInterface;
use TeraBlaze\Database\Legacy\Connectors\MysqliConnector;
use TeraBlaze\Database\Exception\ArgumentException;
use TeraBlaze\Database\Events\InitializeEvent;
use TeraBlaze\Database\Events\PreInitializeEvent;

class DatabaseParcel extends Parcel implements ParcelInterface
{
    /**
     * @throws ArgumentException
     * @throws InvalidContextException
     * @throws ReflectionException
     * @throws ServiceNotFoundException
     */
    public function boot(): void
    {
        $parsed = loadConfig('database');

        foreach ($parsed->get('database.connections') as $key => $conf) {
            $this->initialize($key, $conf);
        }
    }

    /**
     * @param string $confKey
     * @param array<string, mixed> $conf
     * @throws ArgumentException
     * @throws ReflectionException
     * @throws ServiceNotFoundException
     */
    private function initialize(string $confKey, array $conf): void
    {
        $preInitEvent = new PreInitializeEvent($confKey, $conf);
        $this->dispatcher->dispatch($preInitEvent);

        $confKey = $preInitEvent->getConfKey();
        $options = $preInitEvent->getConf();
        $type = $options['type'] ?? $options['driver'] ?? '';

        $connectionName = "database.connection.{$confKey}";
        if (empty($type)) {
            throw new ArgumentException("Database driver type not set");
        }

        switch ($type) {
            case "mysql":
            case "mysqli":
                $dbConnection = new MysqlConnection($options);
                break;
            case "sqlite":
                $dbConnection = new SqliteConnection($options);
                break;
            case "mysql_legacy":
            case "mysqli_legacy":
                $dbConnection = new MysqliConnector($options);
                break;
            default:
                throw new ArgumentException(sprintf("Invalid or unimplemented database type: %s", $type));
        }
        $initEvent = new InitializeEvent($dbConnection);
        $this->dispatcher->dispatch($initEvent);
        $this->container->registerServiceInstance($connectionName, $initEvent->getConnection());
        if (getConfig('database.default') === $confKey) {
            if ($dbConnection instanceof ConnectorInterface) {
                $this->container->setAlias(ConnectorInterface::class, $connectionName);
                return;
            }
            $this->container->setAlias(ConnectionInterface::class, $connectionName);
            $this->container->setAlias('database.connection', $connectionName);
            $this->container->setAlias('database.connection.default', $connectionName);
        }
    }
}

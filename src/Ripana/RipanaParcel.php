<?php

namespace TeraBlaze\Ripana;

use ReflectionException;
use TeraBlaze\Config\Exception\InvalidContextException;
use TeraBlaze\Container\Exception\ServiceNotFoundException;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Ripana\Database\Connection\MysqlConnection;
use TeraBlaze\Ripana\Database\Connection\SqliteConnection;
use TeraBlaze\Ripana\Database\Connectors\MysqliConnector;
use TeraBlaze\Ripana\Database\Exception\ArgumentException;
use TeraBlaze\Ripana\Events\InitializeEvent;
use TeraBlaze\Ripana\Events\PreInitializeEvent;

class RipanaParcel extends Parcel implements ParcelInterface
{
    /**
     * @throws ArgumentException
     * @throws InvalidContextException
     * @throws ReflectionException
     * @throws ServiceNotFoundException
     */
    public function boot(): void
    {
        $parsed = loadConfig('ripana');
        if (empty($parsed)) {
            $parsed = loadConfig('database', 'ripana');
        }

        foreach ($parsed->get('ripana.connections') as $key => $conf) {
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

        $connectionName = "ripana.database.connection.{$confKey}";
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
        if (getConfig('ripana.default') === $confKey) {
            $this->container->setAlias('ripana.database.connection', $connectionName);
            $this->container->setAlias('ripana.database.connection.default', $connectionName);
        }
    }
}

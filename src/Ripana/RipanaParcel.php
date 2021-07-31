<?php

namespace TeraBlaze\Ripana;

use ReflectionException;
use TeraBlaze\Config\Exception\InvalidContextException;
use TeraBlaze\Container\Exception\ServiceNotFoundException;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Ripana\Database\Connectors\MysqliConnector;
use TeraBlaze\Ripana\Database\Exception\ArgumentException;
use TeraBlaze\Ripana\Events\InitializeEvent;
use TeraBlaze\Ripana\Events\PreInitializeEvent;
use TeraBlaze\Ripana\ORM\EntityManager;

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
        $parsed = loadConfigArray('ripana');
        if (empty($parsed)) {
            $parsed = loadConfigArray('database');
        }

        foreach ($parsed as $key => $conf) {
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
        $type = $options['type'] ?? [];

        $connectionName = "ripana.database.connector.{$confKey}";
        $entityManagerName = "ripana.orm.entity_manager.{$confKey}";
        if (empty($type)) {
            throw new ArgumentException("Database driver type not set");
        }

        switch ($type) {
            case "mysql":
            case "mysqli":
                $dbConnection = (new MysqliConnector($options))->setDatabaseConfName($confKey);
                break;
            default:
                throw new ArgumentException(sprintf("Invalid or unimplemented database type: %s", $type));
        }
        $initEvent = new InitializeEvent($dbConnection);
        $this->container->registerServiceInstance($connectionName, $dbConnection);
        $entityManager = new EntityManager($this->container->get($connectionName));
        $this->container->registerServiceInstance($entityManagerName, $entityManager);
        if ($confKey == 'default') {
            $this->container->setAlias('ripana.database.connector', $connectionName);
            $this->container->setAlias('ripana.orm.entity_manager', $entityManagerName);
        }
    }
}

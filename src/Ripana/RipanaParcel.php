<?php

namespace TeraBlaze\Ripana;

use ReflectionException;
use TeraBlaze\Configuration\Configuration;
use TeraBlaze\Configuration\Exception\Argument as ConfigArgumentException;
use TeraBlaze\Configuration\Exception\Syntax;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Container\Exception\ServiceNotFoundException;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Events\Events;
use TeraBlaze\Ripana\Database\Drivers\Mysqli\Connector;
use TeraBlaze\Ripana\Database\Exception\Argument;
use TeraBlaze\Ripana\ORM\EntityManager;

class RipanaParcel extends Parcel implements ParcelInterface
{
    public const RIPANA_INITIALIZE_BEFORE_EVENT = "terablaze.ripana.initialize.before",
        RIPANA_INITIALIZE_AFTER_EVENT = "terablaze.ripana.initialize.after";
    /** @var Container $container */
    private $container;

    protected $type;

    protected $options;

    /**
     * @param ContainerInterface $container
     * @throws Argument
     * @throws ReflectionException
     * @throws ConfigArgumentException
     * @throws Syntax
     * @throws ServiceNotFoundException
     */
    public function build(ContainerInterface $container)
    {
        $this->container = $container;
        /** @var Configuration $configuration */
        $configuration = $this->container->get('configuration');

        if ($configuration) {
            $configuration = $configuration->initialize();
            $parsed = $configuration->parse("config/database");

            foreach ($parsed as $key => $conf) {
                if (!empty($parsed->{$key}) && !empty($parsed->{$key}->type)) {
                    $this->type = $parsed->{$key}->type;
                    $this->options = (array)$parsed->{$key};
                    $this->initialize($key);
                }
            }
        }
    }

    /**
     * @param string $dbConf
     * @throws Argument
     * @throws ReflectionException
     * @throws ServiceNotFoundException
     */
    public function initialize(string $dbConf = "default")
    {
        Events::fire(self::RIPANA_INITIALIZE_BEFORE_EVENT, array($this->type, $this->options));
        $connectionName = "ripana.database.connector.{$dbConf}";
        $entityManagerName = "ripana.orm.entity_manager.{$dbConf}";
        $dbConnection = null;

        if (!$this->type) {
            throw new Argument("Invalid type");
        }

        switch ($this->type) {
            case "mysql":
            case "mysqli": {
                    $dbConnection = (new Connector($this->options))->setDatabaseConfName($dbConf);
                    break;
                }
            default: {
                    throw new Argument("Invalid type");
                }
        }
        $this->container->registerServiceInstance($connectionName, $dbConnection);
        $entityManager = new EntityManager($this->container->get($connectionName));
        $this->container->registerServiceInstance($entityManagerName, $entityManager);
        if ($dbConf == 'default') {
            $this->container->setAlias('ripana.database.connector', $connectionName);
            $this->container->setAlias('ripana.orm.entity_manager', $entityManagerName);
        }
        Events::fire(self::RIPANA_INITIALIZE_AFTER_EVENT, array($this->type, $this->options));
    }
}
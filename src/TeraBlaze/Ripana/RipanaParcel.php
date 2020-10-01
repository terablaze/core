<?php

namespace TeraBlaze\Ripana;

use TeraBlaze\Configuration\Configuration;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Events\Events;
use TeraBlaze\Ripana\Database\Connector\Mysql;
use TeraBlaze\Ripana\Database\Exception\Argument;
use TeraBlaze\Ripana\ORM\EntityManager;

class RipanaParcel extends Parcel implements ParcelInterface
{
    /** @var Container $container */
    private $container;

    protected $type;

    protected $options;

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

    public function initialize(string $dbConf = "default")
    {
        Events::fire("terablaze.ripana.database.initialize.before", array($this->type, $this->options));
        $connectionName = "ripana.database.connection.{$dbConf}";
        $entityManagerName = "ripana.orm.entity_manager.{$dbConf}";
        $dbConnection = null;

        if (!$this->type) {
            throw new Argument("Invalid type");
        }

        switch ($this->type) {
            case "mysql":
            case "mysqli": {
                    $dbConnection = (new Mysql($this->options))->setDatabaseConfName($dbConf);
                    break;
                }
            default: {
                    throw new Argument("Invalid type");
                    break;
                }
        }
        $this->container->registerServiceInstance($connectionName, $dbConnection);
        $entityManager = new EntityManager($this->container->get($connectionName));
        $this->container->registerServiceInstance($entityManagerName, $entityManager);
        Events::fire("terablaze.ripana.database.initialize.after", array($this->type, $this->options));
        return;
    }
}

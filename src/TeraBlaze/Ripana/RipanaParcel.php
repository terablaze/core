<?php

namespace TeraBlaze\Ripana;

use Psr\Container\ContainerInterface;
use TeraBlaze\Configuration\Configuration;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;
use TeraBlaze\Events\Events;
use TeraBlaze\Ripana\Database\Connector\Mysql;
use TeraBlaze\Ripana\Database\Exception\Argument;

class RipanaParcel extends Parcel implements ParcelInterface
{
    /** @var Container $container */
    private $container;

    /**
     * @readwrite
     */
    protected $_type;

    /**
     * @readwrite
     */
    protected $_options;

    public function build(ContainerInterface $container)
    {
        $this->container = $container;
        /** @var Configuration $configuration */
        $configuration = $this->container->get('configuration');

        if ($configuration) {
            $configuration = $configuration->initialize();
            $parsed = $configuration->parse("config/database");

            foreach ($parsed->database as $key => $conf) {
                if (!empty($parsed->database->{$key}) && !empty($parsed->database->{$key}->type)) {
                    $this->type = $parsed->database->{$key}->type;
                    //unset($parsed->database->{$conf}->type);
                    $this->options = (array)$parsed->database->{$key};
                    $this->initialize($key);
                }
            }
        }
    }

    public function initialize(string $dbConf = "default")
    {
        Events::fire("terablaze.ripana.database.initialize.before", array($this->type, $this->options));

        if (!$this->type) {
            throw new Argument("Invalid type");
        }

        Events::fire("terablaze.ripana.database.initialize.after", array($this->type, $this->options));

        switch ($this->type) {
            case "mysql":
            case "mysqli": {
                    $db = new Mysql($this->options);
                    $this->container->registerServiceInstance(get_config('app_id') . '_db_' . $dbConf, $db);
                    $this->container->setAlias('database.connector.' . $dbConf, get_config('app_id') . '_db_' . $dbConf);
                    $this->container->setAlias('database_connector_' . $dbConf, get_config('app_id') . '_db_' . $dbConf);
                    $this->container->setAlias('database.' . $dbConf, get_config('app_id') . '_db_' . $dbConf);
                    $this->container->setAlias('database_' . $dbConf, get_config('app_id') . '_db_' . $dbConf);
                    return $db;
                    break;
                }
            default: {
                    throw new Argument("Invalid type");
                    break;
                }
        }
    }
}

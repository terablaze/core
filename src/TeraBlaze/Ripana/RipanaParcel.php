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

            foreach ($parsed as $key => $conf) {
                if (!empty($parsed->{$key}) && !empty($parsed->{$key}->type)) {
                    $this->type = $parsed->{$key}->type;
                    //unset($parsed->{$conf}->type);
                    $this->options = (array)$parsed->{$key};
                    $this->initialize($key);
                }
            }
        }
    }

    public function initialize(string $dbConf = "default")
    {
        Events::fire("terablaze.ripana.database.initialize.before", array($this->type, $this->options));
        $connectionName = "ripana.database.{$dbConf}";

        if (!$this->type) {
            throw new Argument("Invalid type");
        }

        switch ($this->type) {
            case "mysql":
            case "mysqli": {
                    $this->container->registerServiceInstance($connectionName, new Mysql($this->options));
                    break;
                }
            default: {
                    throw new Argument("Invalid type");
                    break;
                }
        }
        Events::fire("terablaze.ripana.database.initialize.after", array($this->type, $this->options));
    }
}

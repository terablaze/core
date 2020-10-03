<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 1/30/2017
 * Time: 4:07 PM
 */

namespace TeraBlaze\Cache;

use TeraBlaze\Cache\Driver\Memcached;
use TeraBlaze\Cache\Driver\Memcache;
use TeraBlaze\Cache\Driver\File;
use TeraBlaze\Events\Events as Events;
use TeraBlaze\Cache\Exception as Exception;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;

class CacheParcel extends Parcel implements ParcelInterface
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
            $parsed = $configuration->parse("config/cache");

            foreach ($parsed as $key => $conf) {
                if (!empty($parsed->{$key}) && !empty($parsed->{$key}->type)) {
                    $this->type = $parsed->{$key}->type;
                    $this->options = (array)$parsed->{$key};
                    $this->initialize($key);
                }
            }
        }
    }

    public function initialize($cacheConf = "default")
    {
        Events::fire("terablaze.libraries.cache.initialize.before", array($this->type, $this->options));
        $cache = null;

        switch ($this->type) {
            case "memcached": {
                    $cache = new Memcached($this->options);
                    break;
                }
            case "memcache": {
                    $cache = new Memcache($this->options);
                    break;
                }
            case "file": {
                    $cache = new File($this->options);
                    break;
                }
            default: {
                    throw new Exception\Argument("Invalid cache type or cache configuration not properly set in config/cache.php");
                    break;
                }
        }
        $this->container->registerServiceInstance('cache.' . $cacheConf, $cache);
        if ($cacheConf == 'default') {
            $this->container->setAlias('cache', 'cache.default');
        }

        Events::fire("terablaze.libraries.cache.initialize.after", array($this->type, $this->options));
        return;
    }
}

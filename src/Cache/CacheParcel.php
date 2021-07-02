<?php

namespace TeraBlaze\Cache;

use TeraBlaze\Cache\Driver\Memcached;
use TeraBlaze\Cache\Driver\Memcache;
use TeraBlaze\Cache\Driver\File;
use TeraBlaze\Events\Events as Events;
use TeraBlaze\Cache\Exception\Argument as ArgumentException;
use TeraBlaze\Config\Driver\DriverInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;

class CacheParcel extends Parcel implements ParcelInterface
{
    protected $type;

    protected $options;

    public function boot(): void
    {
        /** @var DriverInterface $configuration */
        $configuration = $this->container->get('configuration');

        if ($configuration) {
            $parsed = $configuration->parse("cache");

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
                    throw new ArgumentException("Invalid cache type or cache configuration not properly set in config/cache.php");
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

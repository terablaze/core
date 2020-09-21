<?php
/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 1/30/2017
 * Time: 4:07 PM
 */

namespace TeraBlaze\Libraries;

use TeraBlaze\Base as Base;
use TeraBlaze\Events\Events as Events;
use TeraBlaze\Libraries\Cache\Exception as Exception;
use TeraBlaze\Registry as Registry;

class Cache extends Base
{
	/**
	 * @readwrite
	 */
	protected $_type;
	/**
	 * @readwrite
	 */
	protected $_options;
	
	public function initialize($cache_conf = "default")
	{
		Events::fire("terablaze.libraries.cache.initialize.before", array($this->type, $this->options));
		$type = $this->getType();
		
		if (!$this->type) {
			$configuration = $this->container->get('configuration');
			
			if ($configuration) {
				$configuration = $configuration->initialize();
				$parsed = $configuration->parse("config/cache");
				
				if (!empty($parsed->cache->{$cache_conf}) && !empty($parsed->cache->{$cache_conf}->type)) {
					$this->type = $parsed->cache->{$cache_conf}->type;
					//unset($parsed->cache->{$cache_conf}->type);
					$this->options = (array)$parsed->cache->{$cache_conf};
				}
			}
		}
		
		Events::fire("terablaze.libraries.cache.initialize.after", array($this->type, $this->options));
		
		switch ($this->type) {
			case "memcached": {
				$cache = new Cache\Driver\Memcached($this->options);
				\TeraBlaze\Registry::set(get_config('app_id') . 'cache_' . $cache_conf, $cache);
				return $cache;
				break;
			}
			case "memcache": {
				$cache = new Cache\Driver\Memcache($this->options);
				\TeraBlaze\Registry::set(get_config('app_id') . 'cache_' . $cache_conf, $cache);
				return $cache;
				break;
			}
			case "file": {
				
				$cache = new Cache\Driver\File($this->options);
				\TeraBlaze\Registry::set(get_config('app_id') . 'cache_' . $cache_conf, $cache);
				return $cache;
				break;
			}
			default: {
				throw new Exception\Argument("Invalid cache type or cache configuration not properly set in APP_DIR/configuration/cache.php");
				break;
			}
		}
	}
	
	protected function _getExceptionForImplementation($method)
	{
		return new Exception\Implementation("{$method} method not implemented");
	}
}
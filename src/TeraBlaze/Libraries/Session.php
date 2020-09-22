<?php
/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/4/2017
 * Time: 9:15 AM
 */

namespace TeraBlaze\Libraries;

use TeraBlaze\Base as Base;
use TeraBlaze\Events\Events as Events;
use TeraBlaze\Libraries\Session\Exception as Exception;
use TeraBlaze\Registry as Registry;

class Session extends Base
{
	/**
	 * @readwrite
	 */
	protected $_type;
	
	/**
	 * @readwrite
	 */
	protected $_options;
	
	public function initialize($session_conf = "default")
	{
		if (\TeraBlaze\Registry::get(get_config('app_id') . 'session_' . $session_conf, FALSE)) {
			return \TeraBlaze\Registry::get(get_config('app_id') . 'session_' . $session_conf, FALSE);
		}
		Events::fire("terablaze.libraries.session.initialize.before", array($this->type, $this->options));
		
		if (!$this->type) {
			$configuration = $this->container->get('configuration');
			
			if ($configuration) {
				$configuration = $configuration->initialize();
				$parsed = $configuration->parse("config/session");
				
				if (!empty($parsed->session->{$session_conf}) && !empty($parsed->{$session_conf}->type)) {
					$this->type = $parsed->{$session_conf}->type;
					//unset($parsed->session->{$session_conf}->type);
					$this->options = (array)$parsed->{$session_conf};
				}
			}
		}
		
		Events::fire("terablaze.libraries.session.initialize.after", array($this->type, $this->options));
		
		switch (strtolower($this->type)) {
			case "server": {
				$session = new Session\Driver\Server($this->options);
				\TeraBlaze\Registry::set(get_config('app_id') . 'session_' . $session_conf, $session);
				return $session;
				break;
			}
			case "memcache":
			case "memcached": {
				$session = new Session\Driver\Memcached($this->options);
				\TeraBlaze\Registry::set(get_config('app_id') . 'session_' . $session_conf, $session);
				return $session;
				break;
			}
			case "file": {
				$session = new Session\Driver\File($this->options);
				\TeraBlaze\Registry::set(get_config('app_id') . 'session_' . $session_conf, $session);
				return $session;
				break;
			}
			default: {
				throw new Exception\Argument("Invalid session type or session configuration not properly set in APP_DIR/configuration/session.php");
				break;
			}
		}
	}
	
	protected function _getExceptionForImplementation($method)
	{
		return new Exception\Implementation("{$method} method not implemented");
	}
}
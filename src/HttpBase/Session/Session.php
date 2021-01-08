<?php
/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/4/2017
 * Time: 9:15 AM
 */

namespace TeraBlaze\HttpBase\Session;

use TeraBlaze\Base as Base;
use TeraBlaze\Events\Events as Events;
use TeraBlaze\HttpBase\Session\Exception as Exception;

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
		Events::fire("terablaze.libraries.session.initialize.before", array($this->type, $this->options));
		if ($this->container->has('session')) {
			$session = $this->container->get('session');
			if ($session != null) {
				return $session;
			}
		}
		
		if (!$this->type) {
			$configuration = $this->container->get('configuration');
			
			if ($configuration) {
				$configuration = $configuration->initialize();
				$parsed = $configuration->parse("config/session");
				
				if (!empty($parsed->{$session_conf}) && !empty($parsed->{$session_conf}->type)) {
					$this->type = $parsed->{$session_conf}->type;
					//unset($parsed->session->{$session_conf}->type);
					$this->options = (array)$parsed->{$session_conf};
				}
			}
		}
		
		Events::fire("terablaze.libraries.session.initialize.after", array($this->type, $this->options));
		
		switch (strtolower($this->type)) {
			case "server": {
				$session = new Driver\Server($this->options);
				break;
			}
			case "memcache":
			case "memcached": {
				$session = new Driver\Memcached($this->options);
				break;
			}
			case "file": {
				$session = new Driver\File($this->options);
				break;
			}
			default: {
				throw new Exception\Argument("Invalid session type or session configuration not properly set in APP_DIR/configuration/session.php");
				break;
			}
		}
		$this->container->registerServiceInstance('session', $session);
		return $session;
	}
	
	protected function _getExceptionForImplementation($method)
	{
		return new Exception\Implementation("{$method} method not implemented");
	}
}
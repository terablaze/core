<?php
/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/4/2017
 * Time: 9:15 AM
 */

namespace TeraBlaze\Libraries\Session;

use TeraBlaze\Base as Base;
use TeraBlaze\Events\Events;
use TeraBlaze\Libraries\Session\Driver\File;
use TeraBlaze\Libraries\Session\Driver\Memcached;
use TeraBlaze\Libraries\Session\Driver\Server;
use TeraBlaze\Registry as Registry;
use TeraBlaze\Libraries\Session\Exception as Exception;

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

	protected function _getExceptionForImplementation($method)
	{
		return new Exception\Implementation("{$method} method not implemented");
	}

    /**
     * @param string $session_conf
     * @return mixed|File|Memcached|Server|null
     * @throws Exception\Argument
     */
	public function initialize($session_conf = "default")
	{
		if(\TeraBlaze\Registry::get(get_config('app_id').'session_'.$session_conf, FALSE)){
			return \TeraBlaze\Registry::get(get_config('app_id').'session_'.$session_conf, FALSE);
		}
		Events::fire("terablaze.libraries.session.initialize.before", array($this->type, $this->options));
		
		if (!$this->type)
		{
			$configuration = Registry::get("configuration");

			if ($configuration)
			{
				$configuration = $configuration->initialize();
				$parsed = $configuration->parse("configuration/session");

				if (!empty($parsed->session->{$session_conf}) && !empty($parsed->session->{$session_conf}->type))
				{
					$this->type = $parsed->session->{$session_conf}->type;
					//unset($parsed->session->{$session_conf}->type);
					$this->options = (array) $parsed->session->{$session_conf};
				}
			}
		}
		
		Events::fire("terablaze.libraries.session.initialize.after", array($this->type, $this->options));

		switch (strtolower($this->type))
		{
			case "server":
			{
				$session = new Server($this->options);
				\TeraBlaze\Registry::set(get_config('app_id').'session_'.$session_conf, $session);
				return $session;
				break;
			}
			case "memcache":
			case "memcached":
			{
				$session = new Memcached($this->options);
				\TeraBlaze\Registry::set(get_config('app_id').'session_'.$session_conf, $session);
				return $session;
				break;
			}
			case "file":
			{
				$session = new File($this->options);
				\TeraBlaze\Registry::set(get_config('app_id').'session_'.$session_conf, $session);
				return $session;
				break;
			}
			default:
			{
				throw new Exception\Argument("Invalid session type or session configuration not properly set in APPLICATION_DIR/configuration/session.php");
				break;
			}
		}
	}
}
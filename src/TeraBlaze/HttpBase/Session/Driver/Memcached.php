<?php
/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/4/2017
 * Time: 9:18 AM
 */

namespace TeraBlaze\HttpBase\Session\Driver;

use TeraBlaze\Libraries\Session as Session;

/**
 * Class Server
 * @package TeraBlaze\Libraries\Session\Driver
 */
class Memcached extends Session\Driver
{
	/**
	 * @readwrite
	 */
	protected $_savePath;
	
	/**
	 * @readwrite
	 */
	protected $_prefix;

	/**
	 * @readwrite
	 */
	protected $_type;
	
	/**
	 * @readwrite
	 */
	protected $_duration;
	
	
	// TODO: Add support for multiple servers
	public function __construct($options = array())
	{
		parent::__construct($options);
		switch ($this->_type) {
			case 'memcached':
				ini_set('session.save_handler', 'memcached');
				break;
			case 'memcache':
				ini_set('session.save_handler', 'memcache');
				break;
			default:
				ini_set('session.save_handler', 'memcached');
			
		}

        ini_set('session.save_path', $this->_savePath);

		$TBMemcachedSessionHandler = new Memcached\TBMemcachedSessionHandler();
		session_set_save_handler($TBMemcachedSessionHandler);
		@session_start();
	}
	
	public function get($key, $default = NULL)
	{
		if (isset($_SESSION[$this->_prefix . $key])) {
			return $_SESSION[$this->_prefix . $key];
		}
		
		return $default;
	}
	
	public function set($key, $value = NULL)
	{
		if (is_array($key)) {
			foreach ($key as $new_key => $new_value) {
				$this->set($new_key, $new_value);
			}
		} else {
			$_SESSION[$this->_prefix . $key] = $value;
		}
		return $this;
	}
	
	
	public function get_flash($key, $default = NULL)
	{
		if (isset($_SESSION["TB_flash_" . $this->_prefix . $key])) {
			$flash_data = $_SESSION["TB_flash_" . $this->_prefix . $key];
			unset($_SESSION["TB_flash_" . $this->_prefix . $key]);
			return $flash_data;
		}
		
		return $default;
	}
	
	public function set_flash($key, $value = NULL)
	{
		if (is_array($key)) {
			foreach ($key as $new_key => $new_value) {
				$this->set_flash($new_key, $new_value);
			}
		} else {
			$_SESSION["TB_flash_" . $this->_prefix . $key] = $value;
		}
		return $this;
	}
	
	public function erase($key)
	{
		unset($_SESSION[$this->_prefix . $key]);
		return $this;
	}
}
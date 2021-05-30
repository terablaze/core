<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/4/2017
 * Time: 9:18 AM
 */

namespace TeraBlaze\HttpBase\Session\Driver;

use TeraBlaze\HttpBase\Session as Session;

/**
 * Class Server
 * @package TeraBlaze\HttpBase\Session\Driver
 */
class Server extends Session\Driver
{
    /**
     * @readwrite
     */
    protected $_prefix;

    public function __construct($options = array())
    {
        parent::__construct($options);
        @session_start();
    }

    public function get($key, $default = null)
    {
        if (isset($_SESSION[$this->prefix . $key])) {
            return $_SESSION[$this->prefix . $key];
        }

        return $default;
    }

    public function set($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $new_key => $new_value) {
                $this->set($new_key, $new_value);
            }
        } else {
            $_SESSION[$this->prefix . $key] = $value;
        }
        return $this;
    }


    public function getFlash($key, $default = null)
    {
        if (isset($_SESSION["TB_flash_" . $this->prefix . $key])) {
            $flash_data = $_SESSION["TB_flash_" . $this->prefix . $key];
            unset($_SESSION["TB_flash_" . $this->prefix . $key]);
            return $flash_data;
        }

        return $default;
    }

    public function setFlash($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $new_key => $new_value) {
                $this->setFlash($new_key, $new_value);
            }
        } else {
            $_SESSION["TB_flash_" . $this->prefix . $key] = $value;
        }
        return $this;
    }

    public function erase($key)
    {
        unset($_SESSION[$this->prefix . $key]);
        return $this;
    }
}

<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 1/30/2017
 * Time: 2:34 PM
 */

namespace TeraBlaze\Configuration;

use TeraBlaze\Configuration\Driver\DriverInterface;
use TeraBlaze\Configuration\Driver\Ini;
use TeraBlaze\Configuration\Driver\PHPArray;
use TeraBlaze\Configuration\Exception as Exception;
use TeraBlaze\Core\Kernel\KernelInterface;
use TeraBlaze\Events\Events;

/**
 * Class Configuration
 * @package TeraBlaze
 *
 * loads the configuration to be used by the entire application
 */
class Configuration
{
    protected $type;

    protected $options;

	protected KernelInterface $kernel;

    public function __construct(string $type, KernelInterface $kernel)
    {
        $this->type = $type;
		$this->kernel = $kernel;
    }

    /**
     * @return DriverInterface|Ini|PHPArray
     * @throws Exception\Argument
     */
    public function initialize()
    {
        Events::fire("terablaze.configuration.initialize.before", array($this->type, $this->options));

        if (!$this->type) {
            throw new Exception\Argument("Configuration type not supplied");
        }

        Events::fire("terablaze.configuration.initialize.after", array($this->type, $this->options));

        switch ($this->type) {
            case "ini": {
                    return new Driver\Ini($this->kernel);
                    break;
                }
            case "PhpArray":
            case "PHPArray":
            case "php_array":
            case "phparray": {
                    return new Driver\PHPArray($this->kernel);
                    break;
                }
            default: {
                    throw new Exception\Argument("Invalid type");
                    break;
                }
        }
    }

    /**
     * @param $method
     * @return Exception\Implementation
     */
    protected function _getExceptionForImplementation($method)
    {
        return new Exception\Implementation("{$method} method not implemented");
    }
}

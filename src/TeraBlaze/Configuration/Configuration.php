<?php
/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 1/30/2017
 * Time: 2:34 PM
 */

namespace TeraBlaze\Configuration;

use InvalidArgumentException;
use TeraBlaze\Events\Events;
use TeraBlaze\Configuration\Driver\Ini;
use TeraBlaze\Configuration\Driver\PHPArray;
/**
 * Class Configuration
 * @package TeraBlaze\Configuration
 *
 * loads the configuration to be used by the entire application
 */
class Configuration
{
    /** @var string $type */
    protected $type;

    /**
     * @var
     */
    protected $options;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * @return Ini|PHPArray
     */
    public function initialize()
    {
        Events::fire("terablaze.configuration.initialize.before", array($this->type, $this->options));

        if (!$this->type) {
            throw new InvalidArgumentException("Configuration type not supplied");
        }

        Events::fire("terablaze.configuration.initialize.after", array($this->type, $this->options));

        switch (strtolower($this->type)) {
            case "ini":
            {
                return new Ini($this->options);
                break;
            }
            case "php_array":
            case "phparray":
            case "php-array":
            {
                return new PHPArray($this->options);
                break;
            }
            default:
            {
                throw new InvalidArgumentException("Invalid type");
                break;
            }
        }
    }
}
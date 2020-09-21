<?php

namespace TeraBlaze\Configuration;

trait PolymorphismTrait
{
    /**
     * @param string $methodName
     * @param string[] $arguments
     */
    public function __call(string $methodName, array $arguments)
    {
        $methodPrefix = substr($methodName, 0, 3);
        $property = lcfirst(substr($methodName, 3));
        if ($methodPrefix == 'set' && count($arguments) == 1) {
            if (property_exists($this, $property)) {
                $value = $arguments[0];
                $this->$property = $value;
                return $this;
            }
        }

        if ($methodPrefix == 'get') {
            if (property_exists($this, $property)) {
                if (isset($this->$property)) {
                    return $this->$property;
                }
                return null;
            }
        }

        throw new Exception("Property {$property} not found in class " . get_class($this));
    }

    /**
	 * @param $name
	 * @return mixed
	 */
	public function __get($name)
	{
		$function = "get" . ucfirst($name);
		return $this->$function();
	}
	
	/**
	 * @param $name
	 * @param $value
	 * @return mixed
	 */
	public function __set($name, $value)
	{
		$function = "set" . ucfirst($name);
		return $this->$function($value);
	}
}

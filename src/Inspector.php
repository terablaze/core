<?php

namespace TeraBlaze;

use TeraBlaze\ArrayMethods as ArrayMethods;
use TeraBlaze\StringMethods as StringMethods;

/**
 * Class Inspector
 * @package TeraBlaze
 *
 * handles inspection of class, method and property metas
 */
class Inspector
{
    protected $_class;

    protected array $_meta = [
        "class" => [],
        "properties" => [],
        "methods" => []
    ];

    /** @var array<int, string> $_properties */
    protected array $_properties = [];

    /** @var array<int, string> $_methods */
    protected array $_methods = [];

    /**
     * Inspector constructor.
     * @param $class
     */
    public function __construct($class)
    {
        $this->_class = $class;
    }

    public function getClassName(): string
    {
        $reflection = new \ReflectionClass($this->_class);
        return $reflection->getShortName();
    }

    /**
     * @return array|null
     *
     * gets the metas in the DocComment of a class
     * by wrapping around the _getClassComment() method
     */
    public function getClassMeta()
    {
        if (!isset($_meta["class"])) {
            $comment = $this->_getClassComment();

            if (!empty($comment)) {
                $_meta["class"] = $this->_parse($comment);
            } else {
                $_meta["class"] = null;
            }
        }

        return $_meta["class"];
    }

    /**
     * @return string
     *
     * gets the comments of a class
     */
    protected function _getClassComment()
    {
        $reflection = new \ReflectionClass($this->_class);
        return $reflection->getDocComment();
    }

    /**
     * @param $comment
     * @return array
     *
     * detects and passing the metas in a DocComment for further processing
     */
    protected function _parse($comment)
    {
        $meta = array();
        $pattern = "(@[\w]+[\w\"'@,.:;\\\\\/`= ()_\s]*)";
        $matches = StringMethods::match($comment, $pattern);

        if ($matches != null) {
            foreach ($matches as $match) {
                $parts = ArrayMethods::clean(
                    ArrayMethods::trim(
                        StringMethods::split($match, "[\s([{=]", 2)
                    )
                );

                $meta[$parts[0]] = true;

                if (sizeof($parts) > 1) {
                    $meta[$parts[0]] = ArrayMethods::clean(
                        ArrayMethods::trim(
                            StringMethods::split($parts[1], "[,;]")
                        )
                    );
                }

                if (is_array($meta[$parts[0]]) && !empty($meta[$parts[0]])) {
                    $tempMeta = $meta[$parts[0]];
                    $counter = 0;
                    foreach ($tempMeta as $part) {
                        if (count(StringMethods::split($part, "=")) == 2) {
                            unset($meta[$parts[0]][$counter]);
                            $tempParts = ArrayMethods::clean(
                                ArrayMethods::trim(
                                    StringMethods::split($part, "=")
                                )
                            );
                            $value = $tempParts[1];
                            if (count($tempMeta) - 1 == $counter) {
                                $value = mb_substr($value, 0, -1);
                            }
                            $meta[$parts[0]][$tempParts[0]] = trim($value, " \t\n\r\0\x0B\"");
                        }
                        $counter++;
                    }
                }
            }
        }

        return $meta;
    }

    /**
     * gets the properties of a class
     * by wrapping around the _getClassProperties() method
     *
     * @return array
     */
    public function getClassProperties()
    {
        if (!isset($_properties)) {
            $properties = $this->_getClassProperties();

            foreach ($properties as $property) {
                $_properties[] = $property->getName();
            }
        }

        return $_properties;
    }

    /**
     * @return \ReflectionProperty[]
     *
     * gets the properties of a class
     */
    protected function _getClassProperties()
    {
        $reflection = new \ReflectionClass($this->_class);
        return $reflection->getProperties();
    }

    /**
     * @return array
     *
     * gets the methods of a class
     * by wrapping around the _getClassMethods() method
     */
    public function getClassMethods()
    {
        if (!isset($_methods)) {
            $methods = $this->_getClassMethods();

            foreach ($methods as $method) {
                $_methods[] = $method->getName();
            }
        }

        return $_methods;
    }

    /**
     * @return \ReflectionMethod[]
     *
     * gets the methods of a class
     */
    protected function _getClassMethods()
    {
        $reflection = new \ReflectionClass($this->_class);
        return $reflection->getMethods();
    }

    /**
     * @param $property
     * @return mixed
     *
     * gets the metas in the DocComment of a property
     * by wrapping around the _getPropertyComment() method
     */
    public function getPropertyMeta($property)
    {
        if (!isset($_meta["properties"][$property])) {
            $comment = $this->_getPropertyComment($property);

            if (!empty($comment)) {
                $_meta["properties"][$property] = $this->_parse($comment);
            } else {
                $_meta["properties"][$property] = null;
            }
        }

        return $_meta["properties"][$property];
    }

    /**
     * @param $property
     * @return string
     *
     * gets the comments of a property
     */
    protected function _getPropertyComment($property)
    {
        $reflection = new \ReflectionProperty($this->_class, $property);
        return $reflection->getDocComment();
    }

    /**
     * @param $method
     * @return mixed
     *
     * gets the metas in the DocComment of a method
     * by wrapping around the _getMethodComment() method
     */
    public function getMethodMeta($method)
    {
        if (!isset($_meta["actions"][$method])) {
            $comment = $this->_getMethodComment($method);

            if (!empty($comment)) {
                $_meta["methods"][$method] = $this->_parse($comment);
            } else {
                $_meta["methods"][$method] = null;
            }
        }

        return $_meta["methods"][$method];
    }

    /**
     * @param $method
     * @return string
     *
     * gets the comments of a method
     */
    protected function _getMethodComment($method)
    {
        $reflection = new \ReflectionMethod($this->_class, $method);
        return $reflection->getDocComment();
    }
}

<?php

namespace TeraBlaze;

use ReflectionClass;
use ReflectionException;
use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Support\StringMethods;

/**
 * Class Inspector
 * @package TeraBlaze
 *
 * handles inspection of class, method and property metas
 */
class Inspector
{
    /**
     * @var string|object $class
     */
    protected $class;

    /**
     * @var array<string, mixed> $meta
     */
    protected array $meta = [
        "class" => null,
        "properties" => [],
        "methods" => []
    ];

    /**
     * @var string[]|null $properties
     */
    protected ?array $properties = null;

    /**
     * @var string[]|null $methods
     */
    protected ?array $methods = null;

    /**
     * Inspector constructor.
     * @param string|object $class
     */
    public function __construct($class)
    {
        $this->class = $class;
    }

    /**
     * @throws ReflectionException
     */
    public function getClassName(): string
    {
        $reflection = new ReflectionClass($this->class);
        return $reflection->getShortName();
    }

    /**
     * @return string[]|null
     *
     * gets the metas in the DocComment of a class
     * by wrapping around the getClassComment() method
     */
    public function getClassMeta(): ?array
    {
        if (!isset($this->meta["class"])) {
            $comment = $this->getClassComment();

            if (!empty($comment)) {
                $this->meta["class"] = $this->parse($comment);
            } else {
                $this->meta["class"] = null;
            }
        }

        return $this->meta["class"];
    }

    /**
     * @return string|false
     *
     * gets the comments of a class
     * @throws ReflectionException
     */
    protected function getClassComment()
    {
        $reflection = new ReflectionClass($this->class);
        return $reflection->getDocComment();
    }

    /**
     * @param $comment
     * @return string[]
     *
     * detects and passing the metas in a DocComment for further processing
     */
    protected function parse($comment): array
    {
        $meta = [];
        $pattern = "(@[a-zA-Z]+\s*[a-zA-Z0-9, ()_]*)";
        $matches = StringMethods::match($comment, $pattern);

        if ($matches != null)
        {
            foreach ($matches as $match)
            {
                $parts = ArrayMethods::clean(
                    ArrayMethods::trim(
                        StringMethods::split($match, "[\s]", 2)
                    )
                );

                $meta[$parts[0]] = true;

                if (sizeof($parts) > 1)
                {
                    $meta[$parts[0]] = ArrayMethods::clean(
                        ArrayMethods::trim(
                            StringMethods::split($parts[1], ",")
                        )
                    );
                }
            }
        }

        return $meta;
    }

    /**
     * gets the properties of a class
     *
     * @return string[]
     * @throws ReflectionException
     */
    public function getClassProperties(): ?array
    {
        if (!isset($this->properties)) {
            $properties = (new ReflectionClass($this->class))->getProperties();

            foreach ($properties as $property) {
                $this->properties[] = $property->getName();
            }
        }

        return $this->properties;
    }

    /**
     * gets the methods of a class
     * by wrapping around the doGetClassMethods() method
     *
     * @return array
     * @throws ReflectionException
     */
    public function getClassMethods(): ?array
    {
        if (!isset($this->methods)) {
            $methods = (new ReflectionClass($this->class))->getMethods();

            foreach ($methods as $method) {
                $this->methods[] = $method->getName();
            }
        }

        return $this->methods;
    }

    /**
     * gets the metas in the DocComment of a property
     * by wrapping around the getPropertyComment() method
     *
     * @param string $property
     * @return mixed
     */
    public function getPropertyMeta(string $property)
    {
        if (!isset($this->meta["properties"][$property])) {
            $comment = $this->getPropertyComment($property);

            if (!empty($comment)) {
                $this->meta["properties"][$property] = $this->parse($comment);
            } else {
                $this->meta["properties"][$property] = null;
            }
        }

        return $this->meta["properties"][$property];
    }

    /**
     * @param $property
     * @return string|false
     *
     * gets the comments of a property
     * @throws ReflectionException
     */
    protected function getPropertyComment($property)
    {
        $reflection = new \ReflectionProperty($this->class, $property);
        return $reflection->getDocComment();
    }

    /**
     * @param string $method
     * @return mixed
     *
     * gets the metas in the DocComment of a method
     * by wrapping around the _getMethodComment() method
     */
    public function getMethodMeta(string $method)
    {
        if (!isset($this->meta["methods"][$method])) {
            $comment = $this->getMethodComment($method);

            if (!empty($comment)) {
                $this->meta["methods"][$method] = $this->parse($comment);
            } else {
                $this->meta["methods"][$method] = null;
            }
        }

        return $this->meta["methods"][$method];
    }

    /**
     * @param $method
     * @return string|false
     *
     * gets the comments of a method
     * @throws ReflectionException
     */
    protected function getMethodComment($method)
    {
        $reflection = new \ReflectionMethod($this->class, $method);
        return $reflection->getDocComment();
    }
}

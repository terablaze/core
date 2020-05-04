<?php

namespace TeraBlaze\Container;

use ReflectionClass;
use ReflectionException;
use TeraBlaze\Container\Exception\ContainerException;
use TeraBlaze\Container\Exception\InvalidArgumentException;
use TeraBlaze\Container\Exception\ParameterNotFoundException;
use TeraBlaze\Container\Exception\ServiceNotFoundException;

/**
 * The container interface. This extends the interface defined by
 * `psr-11` to include methods for retrieving parameters.
 */
class Container implements ContainerInterface
{
    /**
     * @var self $instance
     */
    private static $instance;

    /**
     * @var array
     */
    private $services;

    /**
     * @var array
     */
    private $serviceAliases;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var array
     */
    private $serviceInstances;

    /**
     * @var array
     */
    private $resolvedParameters;

    /**
     * Constructor for the container.
     *
     * Entries into the $services array must be an associative array with a
     * 'class' key and an optional 'arguments' key. Where present the arguments
     * will be passed to the class constructor. If an argument is an instance of
     * ContainerService the argument will be replaced with the corresponding
     * service from the container before the class is instantiated. If an
     * argument is an instance of ContainerParameter the argument will be
     * replaced with the corresponding parameter from the container before the
     * class is instantiated.
     *
     * @param array $services The service definitions.
     * @param array $parameters The parameter definitions.
     */
    private function __construct(array $services = [], array $parameters = [])
    {
        $this->services = $services;
        $this->parameters = $parameters;
        $this->serviceInstances = [];

        foreach ($services as $key => $service) {
            $this->setAliasInternally($key, $service);
        }
    }

    /**
     * @param string $key
     * @param mixed[] $service
     */
    private function setAliasInternally(string $key, array $service): void
    {
        $alias = $service['alias'] ?? $service['class'];
        $this->serviceAliases[$alias] = $key;
    }

    /**
     * @param string $alias
     * @param string $name
     * @return Container
     * @throws ServiceNotFoundException
     */
    public function setAlias(string $alias, string $name): self
    {
        if (!$this->has($name)) {
            throw new ServiceNotFoundException('Setting alias for an unregistered service: ' . $name);
        }
        $this->serviceAliases[$alias] = $name;

        return $this;
    }

    /**
     * @param array $services
     * @param array $parameters
     * @return Container
     */
    public static function getContainer(array $services = [], array $parameters = []): self
    {
        if (empty(self::$instance)) {
            self::$instance = new self($services, $parameters);
        }
        return self::$instance;
    }

    /**
     * @param array $registrant
     * @return Container
     * @throws InvalidArgumentException
     */
    public function register(array $registrant): self
    {
        foreach ($registrant as $type => $values) {
            switch ($type) {
                case 'service':
                case 'services':
                    foreach ($values as $key => $value) {
                        $this->registerService($key, $value);
                    }
                    break;
                case 'parameter':
                case 'parameters':
                    foreach ($values as $key => $value) {
                        $this->registerParameter($key, $value);
                    }
                    break;
                default:
                    throw new InvalidArgumentException('Invalid registration type: ' . $type);
            }
        }
        return $this;
    }

    /**
     * @param string $key
     * @param array $service
     * @return Container
     */
    public function registerService(string $key, array $service): self
    {
        $this->services[$key] = $service;
        $this->setAliasInternally($key, $service);
        return $this;
    }

    /**
     * @param string $key
     * @param object $service
     * @return Container
     */
    public function registerServiceInstance(string $key, object $service): self
    {
        $this->serviceInstances[$key] = $service;
        return $this;
    }

    /**
     * @param string $key
     * @param mixed $parameter
     * @return Container
     */
    public function registerParameter(string $key, $parameter): self
    {
        if (array_key_exists($key, $this->parameters)) {
            $newValue = (is_array($parameter)) ?
                array_merge($this->parameters[$key], $parameter) :
                array_merge($this->parameters[$key], [$parameter]);
        } else {
            $newValue = $parameter;
        }
        $this->parameters[$key] = $newValue;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @throws ReflectionException
     */
    public function get($name)
    {
        if (!$this->has($name)) {
            throw new ServiceNotFoundException('Service not found: ' . $name);
        }

        // If we haven't created it, create it and save to store
        if (!isset($this->serviceInstances[$name]) || !isset($this->serviceInstances[$this->serviceAliases[$name]])) {
            $this->serviceInstances[$name] = $this->createService($name);
        }

        // Return service from store
        return $this->serviceInstances[$name];
    }

    /**
     * {@inheritDoc}
     */
    public function has($name)
    {
        return isset($this->services[$name]) || isset($this->services[$this->serviceAliases[$name]]);
    }

    /**
     * {@inheritDoc}
     */
    public function getParameter($name)
    {
        // If we haven't created it, create it and save to store
        if (!isset($this->resolvedParameters[$name])) {

            $tokens = explode('.', $name);
            $context = $this->parameters;

            while (null !== ($token = array_shift($tokens))) {
                if (!isset($context[$token])) {
                    throw new ParameterNotFoundException('Parameter not found: ' . $name);
                }

                $context = $context[$token];
            }

            if (self::isParameter($context)) {
                $context = $this->getParameter(self::cleanParameterReference($context));
            }

            $this->resolvedParameters[$name] = $context;
        }

        return $this->resolvedParameters[$name];
    }

    /**
     * {@inheritDoc}
     */
    public function hasParameter($name): bool
    {
        try {
            $this->getParameter($name);
        } catch (ParameterNotFoundException $exception) {
            return false;
        } catch (ContainerException $e) {
            return false;
        }

        return true;
    }

    /**
     * Attempt to create a service.
     *
     * @param string $name The service name.
     *
     * @return mixed The created service.
     *
     * @throws ParameterNotFoundException
     * @throws ContainerException On failure.
     * @throws ReflectionException
     */
    private function createService(string $name)
    {
        $entry = &$this->services[$name];

        if (!is_array($entry) || !isset($entry['class'])) {
            throw new ContainerException($name . ' service entry must be an array containing a \'class\' key');
        } elseif (!class_exists($entry['class'])) {
            throw new ContainerException($name . ' service class does not exist: ' . $entry['class']);
        } elseif (isset($entry['lock'])) {
            throw new ContainerException($name . ' contains circular reference');
        }

        $entry['lock'] = true;

        $arguments = isset($entry['arguments']) ? $this->resolveArguments($entry['arguments']) : [];

        $reflector = new ReflectionClass($entry['class']);
        $service = $reflector->newInstanceArgs($arguments);

        if (isset($entry['calls'])) {
            $this->initializeService($service, $name, $entry['calls']);
        }

        return $service;
    }

    /**
     * Resolve argument definitions into an array of arguments.
     *
     * @param array $argumentDefinitions The service arguments definition.
     *
     * @return array The service constructor arguments.
     *
     * @throws ParameterNotFoundException
     * @throws ContainerException
     * @throws ReflectionException
     */
    private function resolveArguments(array $argumentDefinitions): array
    {
        $arguments = [];

        foreach ($argumentDefinitions as $argumentDefinition) {
            if (self::isService($argumentDefinition)) {
                $arguments[] = $this->get(self::cleanServiceReference($argumentDefinition));
            } elseif (self::isParameter($argumentDefinition)) {
                $arguments[] = $this->getParameter(self::cleanParameterReference($argumentDefinition));
            } else {
                $arguments[] = $argumentDefinition;
            }
        }

        return $arguments;
    }

    /**
     * Initialize a service using the call definitions.
     *
     * @param object $service The service.
     * @param string $name The service name.
     * @param array $callDefinitions The service calls definition.
     *
     * @throws ContainerException On failure.
     * @throws ParameterNotFoundException
     * @throws ReflectionException
     */
    private function initializeService($service, string $name, array $callDefinitions): void
    {
        foreach ($callDefinitions as $callDefinition) {
            if (!is_array($callDefinition) || !isset($callDefinition['method'])) {
                throw new ContainerException($name . ' service calls must be arrays containing a \'method\' key');
            } elseif (!is_callable([$service, $callDefinition['method']])) {
                throw new ContainerException($name . ' service asks for call to uncallable method: ' . $callDefinition['method']);
            }

            $arguments = isset($callDefinition['arguments']) ? $this->resolveArguments($callDefinition['arguments']) : [];

            call_user_func_array([$service, $callDefinition['method']], $arguments);
        }
    }

    /**
     * Checks to see if a string is formatted as a parameter reference
     * i.e starts and ends with '%'
     * @param string $name
     * @return bool
     */
    private static function isParameter(string $name): bool
    {
        return mb_substr($name, 0, 1) === '%' && mb_substr($name, -1) === '%';
    }

    /**
     * Formats a string reference to a usable string
     * i.e gets rid of the preceding '@'
     *
     * @param string $name
     * @return string
     */
    private static function cleanParameterReference(string $name): string
    {
        return mb_substr($name, 1, -1);
    }

    /**
     * Checks to see if a string is formatted as a service reference
     * i.e starts with '@'
     * @param string $name
     * @return bool
     */
    private static function isService(string $name): bool
    {
        return mb_substr($name, 0, 1) === '@';
    }

    /**
     * Formats a string reference to a usable string
     * i.e gets rid of the preceding '@'
     *
     * @param string $name
     * @return string
     */
    private static function cleanServiceReference(string $name): string
    {
        return mb_substr($name, 1);
    }
}
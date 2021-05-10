<?php

namespace TeraBlaze\Container;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionUnionType;
use TeraBlaze\Container\Exception\ContainerException;
use TeraBlaze\Container\Exception\DependencyIsNotInstantiableException;
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
     */
    private function __construct()
    {
        $this->serviceInstances = [];

        $this->registerServiceInstance('terablaze.container', $this);
    }

    /**
     * Sets a service alias internally based on "alias" key
     * or the class name of the service alias is not set
     *
     * @param string $key
     * @param mixed[] $service
     */
    private function setAliasInternally(string $key, array $service): void
    {
        $alias = $service['alias'] ?? $service['class'] ?? $key;
        $this->serviceAliases[$alias] = $key;
    }

    /**
     * Sets the alias of a registered service
     *
     * @param string $alias The alias to set
     * @param string $name The registered service which the alias is being set
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
     * Static method which returns an instance of the container
     *
     * @param array $services
     * @param array $parameters
     * @return Container
     */
    public static function getContainer(array $services = [], array $parameters = []): self
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        self::$instance->services = array_merge(self::$instance->services ?? [], $services);
        self::$instance->parameters = array_merge(self::$instance->parameters ?? [], $parameters);
        foreach ($services as $key => $service) {
            self::$instance->setAliasInternally($key, $service);
        }
        return self::$instance;
    }

    /**
     * Registers services specified in the $servicesToRegister array
     * by calling the registerService() method (not the instances)
     *
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
     * Registers a single service (not the instance)
     *
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
     * Registers a new service instance
     *
     * @param string|object $key
     * @param object|null $service
     * @return Container
     */
    public function registerServiceInstance($key, object $service = null): self
    {
        if (is_object($key)) {
            $service = $key;
            $key = get_class($key);
        }
        $class = [
            'class' => get_class($service),
        ];
        if (!$this->has($key)) {
            $this->registerService($key, $class);
        }
        $this->serviceInstances[$key] = $service;
        return $this;
    }

    /**
     * Registers a parameter
     *
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

        if (isset($this->serviceInstances[$name])) {
            // Return service from store
            return $this->serviceInstances[$name];
        }

        $resolvedAlias = $this->serviceAliases[$name] ?? null;

        if (isset($this->serviceInstances[$resolvedAlias])) {
            // Return service from store
            return $this->serviceInstances[$resolvedAlias];
        }

        if (isset($this->services[$name])) {
            $this->serviceInstances[$name] = $this->createService($name);
        } else {
            $name = $resolvedAlias;
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
        return isset($this->services[$name]) || isset($this->serviceAliases[$name]);
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

            if (!is_string($context)) {
                // TODO: Resolve parameters
            } elseif ($this->isParameter($context)) {
                $context = $this->getParameter($this->cleanParameterReference($context));
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
     * Attempt to create/instantiate a service.
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
        $reflectionParameters = [];
        $class = $entry['class'] ?? $name;

        if (!is_array($entry)) {
            throw new ContainerException($name . ' service entry must be an array');
        } elseif (!class_exists($class)) {
            throw new ContainerException($name . ' service class does not exist: ' . $class);
        } elseif (isset($entry['lock'])) {
            throw new ContainerException($name . ' contains circular reference');
        }

        $entry['lock'] = true;

        $reflector = new ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new DependencyIsNotInstantiableException("Cannot instantiate service {$class}");
        }
        $constructor = $reflector->getConstructor();

        if (!is_null($constructor)) {
            $reflectionParameters = $constructor->getParameters();
        }

        if (is_null($constructor) || empty($reflectionParameters)) {
            // create new instance without passing arguments to the constructor
            $service = $reflector->newInstance();
        } else {
            $registeredArguments = $entry['arguments'] ?? [];
            $arguments = $this->resolveArguments($registeredArguments, $reflectionParameters);
            $service = $reflector->newInstanceArgs($arguments);
        }

        if (isset($entry['calls'])) {
            $this->initializeServiceCalls($service, $entry['calls'], $name);
        }

        return $service;
    }

    /**
     * Resolve argument definitions into an array of arguments.
     *
     * @param array $argumentDefinitions The service arguments definition.
     *
     * @param ReflectionParameter[] $reflectionParameters
     * @return array The service constructor arguments.
     *
     * @throws ContainerException
     * @throws ParameterNotFoundException
     * @throws ReflectionException
     */
    public function resolveArguments(array $argumentDefinitions, array $reflectionParameters = []): array
    {
        $arguments = [];
        $resolvedArguments = [];

        foreach ($argumentDefinitions as $key => $argumentDefinition) {
            if (is_array($argumentDefinition)) {
                return [$this->resolveArguments($argumentDefinition)];
            }
            if (is_string($argumentDefinition) && $this->isService($argumentDefinition)) {
                $arguments[$key] = $this->get($this->cleanServiceReference($argumentDefinition));
            } elseif (is_string($argumentDefinition) && $this->isParameter($argumentDefinition)) {
                $arguments[$key] = $this->getParameter($this->cleanParameterReference($argumentDefinition));
            } else {
                $arguments[$key] = $argumentDefinition;
            }
        }

        if (count($argumentDefinitions) === count($reflectionParameters)) {
            return $arguments;
        }

        // if $arguments is empty, initialize with 1 so that we can loop through it later
        if (empty($arguments)) {
            $arguments = [1];
        }

        // Loops through the details of reflectionParameters
        foreach ($reflectionParameters as $reflectionParameter) {
            $name = $reflectionParameter->getName();
            $position = $reflectionParameter->getPosition();
            $type = $reflectionParameter->getType();
            if ($type instanceof ReflectionUnionType) {
                $unionTypes = $type->getTypes();
                foreach ($unionTypes as $aType) {
                    if (!$aType->isBuiltin()) {
                        $typesString = implode(" | ", $unionTypes);
                        throw new InvalidArgumentException("Cannot use non built in types ({$typesString}) for union types when auto-wiring");
                    }
                }
                $type = $unionTypes[0];
            }
            $typeName = $type->getName();
            try {
                $defaultValue = $reflectionParameter->getDefaultValue();
            } catch (ReflectionException $reflectionException) {
                unset($defaultValue);
            }
            $resolvedArgument = $defaultValue ?? $typeName;
            foreach ($arguments as $key => $argument) {
                if (is_object($argument) && ($typeName == get_class($argument) || $argument instanceof $typeName || $argument instanceof $key)) {
                    $resolvedArgument = $argument;
                    continue;
                }
                if (!is_object($argument) && $name === $key) {
                    $resolvedArgument = $argument;
                    continue;
                }
                if (is_string($resolvedArgument) && class_exists($resolvedArgument)) {
                    if (!$this->has($typeName)) {
                        $this->registerService($typeName, ['class' => $typeName]);
                    }
                    $resolvedArgument = $this->get($typeName);
                    continue;
                }
                if (!is_object($argument)) {
                    continue;
                }
            }
            $resolvedArguments[] = $resolvedArgument;
        }

        if (count($resolvedArguments) == 0) {
            return $arguments;
        }

        return $resolvedArguments;
    }

    /**
     * Initialize a service using the call definitions.
     *
     * @param object $service The service.
     * @param array $callDefinitions The service calls definition.
     * @param string|null $name The service name.
     *
     * @return object|mixed
     * @throws ContainerException On failure.
     * @throws ParameterNotFoundException
     * @throws ReflectionException
     */
    public function initializeServiceCalls(object $service, array $callDefinitions, string $name = null)
    {
        $serviceName = $name ?? get_class($service);
        foreach ($callDefinitions as $callDefinition) {
            if (!is_array($callDefinition) || !isset($callDefinition['method'])) {
                throw new ContainerException($serviceName . ' service calls must be arrays containing a \'method\' key');
            } elseif (!is_callable([$service, $callDefinition['method']])) {
                throw new ContainerException($serviceName . ' service asks for call to uncallable method: ' . $callDefinition['method']);
            }

            $reflectionMethod = new ReflectionMethod($service, $callDefinition['method']);
            $reflectionParameters = $reflectionMethod->getParameters();

            if (empty($reflectionParameters)) {
                call_user_func([$service, $callDefinition['method']]);
                continue;
            }

            $methodArguments = $this->resolveArguments($callDefinition['arguments'] ?? [], $reflectionParameters);

            call_user_func_array([$service, $callDefinition['method']], $methodArguments);
        }
        return $service;
    }

    /**
     * Checks to see if a string is formatted as a parameter reference
     * i.e starts and ends with '%'
     * @param string $name
     * @return bool
     */
    private function isParameter(string $name): bool
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
    private function cleanParameterReference(string $name): string
    {
        return mb_substr($name, 1, -1);
    }

    /**
     * Checks to see if a string is formatted as a service reference
     * i.e starts with '@'
     * @param string $name
     * @return bool
     */
    private function isService(string $name): bool
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
    private function cleanServiceReference(string $name): string
    {
        return mb_substr($name, 1);
    }

    public function getService(string $name): array
    {
        if (isset($this->services[$name])) {
            return $this->services[$name];
        }
        $resolvedAlias = $this->serviceAliases[$name] ?? null;
        return $this->services[$resolvedAlias] ?? [];
    }
}
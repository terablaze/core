<?php

namespace TeraBlaze\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use TeraBlaze\ArrayMethods;
use TeraBlaze\Container\Exception\ContainerException;
use TeraBlaze\Container\Exception\DependencyIsNotInstantiableException;
use TeraBlaze\Container\Exception\InvalidArgumentException;
use TeraBlaze\Container\Exception\ParameterNotFoundException;
use TeraBlaze\Container\Exception\ServiceNotFoundException;
use TeraBlaze\Ripana\ORM\ModelInterface;

/**
 * The container interface. This extends the interface defined by
 * `psr-11` to include methods for retrieving parameters.
 */
class Container implements ContainerInterface
{
    /**
     * @var Container|null
     */
    private static ?Container $instance;

    /**
     * @var array<string, mixed>
     */
    private array $services = [];

    /**
     * @var array<string, string>
     */
    private array $serviceAliases = [];

    /**
     * @var object[] $resolvedServices
     */
    private array $resolvedServices;

    /**
     * @var array<string, mixed>
     */
    private array $parameters = [];

    /**
     * @var array<string, mixed>
     */
    private array $resolvedParameters = [];

    /**
     * Constructor for the container.
     *
     * Entries into the $services array must be an associative array with a
     * 'class' key and an optional 'arguments' key. Where present, the arguments
     * will be passed to the class constructor. If an argument is an instance of
     * ContainerService the argument will be replaced with the corresponding
     * service from the container before the class is instantiated. If an
     * argument is an instance of ContainerParameter the argument will be
     * replaced with the corresponding parameter from the container before the
     * class is instantiated.
     * @throws ServiceNotFoundException
     */
    private function __construct()
    {
        $this->resolvedServices = [];

        $this->registerServiceInstance('terablaze.container', $this);
        $interfaces = ArrayMethods::wrap(class_implements($this));
        foreach ($interfaces as $interface) {
            $this->setAlias($interface, 'terablaze.container');
        }
    }

    /**
     * Sets a service alias internally based on "alias" key
     * or the class name of the service alias is not set
     *
     * @param string $key
     * @param array<string|int, mixed> $service
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
        if ($this->serviceAliases[$alias] ?? '' !== $name) {
            $this->serviceAliases[$alias] = $name;
        }
        return $this;
    }

    /**
     * Static method which returns an instance of the container
     *
     * @param array<string, mixed> $services
     * @param array<string, mixed> $parameters
     * @return ContainerInterface
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
     * @param array<string|int, mixed> $registrant
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
     * @param array<string, mixed> $service
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
        $this->resolvedServices[$key] = $service;
        return $this;
    }

    /**
     * @param object|string $name
     * @return $this
     */
    public function removeService($name): self
    {
        if (is_object($name)) {
            $name = get_class($name);
        }
        $possibleInstanceKeys = [];
        foreach ($this->services as $key => $value) {
            if ($key == $name) {
                unset($this->services[$key]);
                $possibleInstanceKeys[] = $key;
            }
        }
        foreach ($this->serviceAliases as $key => $value) {
            if ($key == $name || $value == $name) {
                unset($this->serviceAliases[$key]);
                $possibleInstanceKeys[] = $value;
            }
        }
        foreach ($possibleInstanceKeys as $possibleInstanceKey) {
            unset($this->resolvedServices[$possibleInstanceKey]);
        }
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
            $newValue = array_merge($this->parameters[$key], ArrayMethods::wrap($parameter));
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
    public function get($id): object
    {
        if (!$this->has($id)) {
            throw new ServiceNotFoundException('ServiceException not found: ' . $id);
        }

        if (isset($this->resolvedServices[$id])) {
            // Return service from store
            return $this->resolvedServices[$id];
        }

        $resolvedAlias = $this->serviceAliases[$id] ?? null;

        if (isset($this->resolvedServices[$resolvedAlias])) {
            // Return service from store
            return $this->resolvedServices[$resolvedAlias];
        }

        $id = isset($this->services[$id]) ? $id : $resolvedAlias;
        $this->resolvedServices[$id] = $this->createService($id);

        // Return service from store
        return $this->resolvedServices[$id];
    }

    /**
     * {@inheritDoc}
     */
    public function has($id): bool
    {
        return isset($this->services[$id]) || isset($this->serviceAliases[$id]);
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
        } catch (ParameterNotFoundException | ContainerException $exception) {
            return false;
        }

        return true;
    }

    /**
     * Initialize a route action
     *
     * @param callable $callable The service.
     * @param array<string, mixed> $parameters The call parameters
     *
     * @return false|mixed
     *
     * @throws ContainerException
     * @throws ParameterNotFoundException
     * @throws ReflectionException
     */
    public function call(callable $callable, array $parameters = [])
    {
        $reflector = $this->getReflector($callable);

        $reflectionParameters = $reflector->getParameters();

        if (empty($reflectionParameters)) {
            return call_user_func($callable);
        }

        $methodArguments = $this->resolveArguments($parameters, $reflectionParameters);

        return call_user_func_array($callable, $methodArguments);
    }

    /**
     * @param string $service
     * @param array<string, mixed> $definition
     * @param bool $replace
     * @return object
     * @throws ReflectionException
     */
    public function make(string $service, array $definition = [], bool $replace = false): object
    {
        if ($this->has($service) && $replace) {
            $this->removeService($service);
        }
        if (!$this->has($service)) {
            if (empty($definition['class'])) {
                $definition['class'] = $service;
            }
            $this->registerService(
                $service,
                $definition
            );
        }

        return $this->get($service);
    }

    /**
     * @param string $name
     * @return array<string, mixed>
     */
    public function getServiceRegistration(string $name): array
    {
        if (isset($this->services[$name])) {
            return $this->services[$name];
        }
        $resolvedAlias = $this->serviceAliases[$name] ?? null;
        return $this->services[$resolvedAlias] ?? [];
    }

    /**
     * Attempt to create/instantiate a service.
     *
     * @param string $name The service name.
     *
     * @return object The created service.
     *
     * @throws ParameterNotFoundException
     * @throws ContainerException On failure.
     * @throws ReflectionException
     */
    private function createService(string $name): object
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
            throw new DependencyIsNotInstantiableException("Cannot instantiate service $class");
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
     * @param array<string|int, mixed> $argumentDefinitions The service arguments definition.
     *
     * @param ReflectionParameter[] $reflectionParameters
     * @return array<int, mixed> The resolved arguments.
     *
     * @throws ContainerException
     * @throws ParameterNotFoundException
     * @throws ReflectionException
     */
    private function resolveArguments(array $argumentDefinitions, array $reflectionParameters = []): array
    {
        $arguments = [];
        $resolvedArguments = [];

        foreach ($argumentDefinitions as $key => $argumentDefinition) {
            if (is_array($argumentDefinition)) {
                $argumentDefinition = $this->resolveArguments($argumentDefinition);
            }
            if (is_string($argumentDefinition) && $this->isService($argumentDefinition)) {
                $arguments[$key] = $this->get($this->cleanServiceReference($argumentDefinition));
            } elseif (is_string($argumentDefinition) && $this->isParameter($argumentDefinition)) {
                $arguments[$key] = $this->getParameter($this->cleanParameterReference($argumentDefinition));
            } else {
                if (is_array($argumentDefinition)) {
                    $argumentDefinition = $this->resolveArguments($argumentDefinition);
                }
                $arguments[$key] = $argumentDefinition;
            }
        }

//        if (count($argumentDefinitions) === count($reflectionParameters)) {
//            return $arguments;
//        }

        // Loops through the details of reflectionParameters
        foreach ($reflectionParameters as $reflectionParameter) {
            $name = $reflectionParameter->getName();
//            $position = $reflectionParameter->getPosition(); TODO: Deal with parameter position
            $types = $this->getAllReflectionTypes($reflectionParameter);
            if (count($types) > 1) {
                foreach ($types as $aType) {
                    if (!$aType->isBuiltin()) {
                        $typesString = implode(" | ", $types);
                        throw new InvalidArgumentException(
                            "Cannot use non built in types included in " .
                            "($typesString) for union types when auto-wiring"
                        );
                    }
                }
            }
            $type = $types[0] ?? null;
            $typeName = is_null($type) ? null : $type->getName();
            $resolvedType = $this->resolveType($typeName);

            if ($reflectionParameter->isDefaultValueAvailable()) {
                $defaultValue = $reflectionParameter->getDefaultValue();
            }

            $resolvedArgument = $defaultValue ?? $resolvedType;
            foreach ($arguments as $key => $argument) {
                if (is_a($typeName, ModelInterface::class, true)) {
                    unset($arguments[$key]);
                    $resolvedArgument = $typeName::find($argument);
                    if (is_null($resolvedArgument)) {
                        throw new InvalidArgumentException(
                            sprintf('No model found with the specified id: %s', $argument)
                        );
                    }
                    break;
                }
                $typeName = (string)$typeName;
                if (
                    ($name === $key) ||
                    (is_object($argument) && ($argument instanceof $typeName)) ||
                    (!is_object($resolvedArgument) && is_int($key))
                ) {
                    unset($arguments[$key]);
                    $resolvedArgument = $argument;
                    break;
                }
            }
            $resolvedArguments[] = $resolvedArgument;
        }

        if (count($resolvedArguments) == 0) {
            return array_values($arguments);
        }

        return $resolvedArguments;
    }

    /**
     * @param ReflectionParameter $reflectionParameter
     * @return ReflectionNamedType[]|ReflectionType[]
     */
    private function getAllReflectionTypes(ReflectionParameter $reflectionParameter): array
    {
        $reflectionType = $reflectionParameter->getType();

        if (!$reflectionType) {
            return [];
        }

        return $reflectionType instanceof ReflectionUnionType
            ? $reflectionType->getTypes()
            : [$reflectionType];
    }

    /**
     * @param string|null $typeName
     * @return object|null
     * @throws ReflectionException
     */
    private function resolveType(?string $typeName): ?object
    {
        if (
            is_null($typeName) ||
            (!class_exists($typeName) && !interface_exists($typeName))
        ) {
            return null;
        }
        if (!$this->has($typeName) && class_exists($typeName)) {
            $this->registerService($typeName, ['class' => $typeName]);
        }
        if ($this->has($typeName)) {
            return $this->get($typeName);
        }
        return null;
    }

    /**
     * @param mixed $callable
     *
     * @return ReflectionMethod|ReflectionFunction
     * @throws ReflectionException
     */
    private function getReflector($callable)
    {
        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], $callable[1]);
        }

        return new ReflectionFunction($callable);
    }

    /**
     * Initialize a service using the call definitions.
     *
     * @param object $service The service.
     * @param array<string|int, mixed> $callDefinitions The service calls definition.
     * @param string|null $name The service name.
     *
     * @return void
     * @throws ContainerException On failure.
     * @throws ParameterNotFoundException
     * @throws ReflectionException
     */
    private function initializeServiceCalls(object $service, array $callDefinitions, string $name = null): void
    {
        $serviceName = $name ?? get_class($service);
        foreach ($callDefinitions as $callDefinition) {
            if (!is_array($callDefinition) || !isset($callDefinition['method'])) {
                throw new ContainerException(
                    $serviceName . ' service calls must be arrays containing a \'method\' key'
                );
            } elseif (!is_callable([$service, $callDefinition['method']])) {
                throw new ContainerException(
                    $serviceName . ' service asks for call to uncallable method: ' . $callDefinition['method']
                );
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
}

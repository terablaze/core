<?php

namespace TeraBlaze\Database;

use TeraBlaze\Console\Command;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Support\ArrayMethods;
use InvalidArgumentException;

abstract class Seeder
{
    /**
     * The container instance.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * The console command instance.
     *
     * @var Command
     */
    protected $command;

    /**
     * Run the given seeder class.
     *
     * @param  array|string  $class
     * @param  bool  $silent
     * @param  array  $parameters
     * @return $this
     */
    public function call($class, $silent = false, array $parameters = [])
    {
        $classes = ArrayMethods::wrap($class);

        foreach ($classes as $class) {
            $seeder = $this->resolve($class);

            $name = get_class($seeder);

            if ($silent === false && isset($this->command)) {
                $this->command->getOutput()->writeln("<comment>Seeding:</comment> {$name}");
            }

            $startTime = microtime(true);

            $seeder->__invoke($parameters);

            $runTime = number_format((microtime(true) - $startTime) * 1000, 2);

            if ($silent === false && isset($this->command)) {
                $this->command->getOutput()->writeln("<info>Seeded:</info>  {$name} ({$runTime}ms)");
            }
        }

        return $this;
    }

    /**
     * Run the given seeder class.
     *
     * @param  array|string  $class
     * @param  array  $parameters
     * @return void
     */
    public function callWith($class, array $parameters = [])
    {
        $this->call($class, false, $parameters);
    }

    /**
     * Silently run the given seeder class.
     *
     * @param  array|string  $class
     * @param  array  $parameters
     * @return void
     */
    public function callSilent($class, array $parameters = [])
    {
        $this->call($class, true, $parameters);
    }

    /**
     * Resolve an instance of the given seeder class.
     *
     * @param  string  $class
     * @return self
     */
    protected function resolve($class)
    {
        if (isset($this->container)) {
            $instance = $this->container->make($class);

            $instance->setContainer($this->container);
        } else {
            $instance = new $class;
        }

        if (isset($this->command)) {
            $instance->setCommand($this->command);
        }

        return $instance;
    }

    /**
     * Set the IoC container instance.
     *
     * @param  ContainerInterface $container
     * @return $this
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Set the console command instance.
     *
     * @param  Command  $command
     * @return $this
     */
    public function setCommand(Command $command)
    {
        $this->command = $command;

        return $this;
    }

    /**
     * Run the database seeds.
     *
     * @param  array  $parameters
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function __invoke(array $parameters = [])
    {
        if (! method_exists($this, 'run')) {
            throw new InvalidArgumentException('Method [run] missing from '.get_class($this));
        }

        return isset($this->container)
                    ? $this->container->call([$this, 'run'], $parameters)
                    : $this->run(...$parameters);
    }
}

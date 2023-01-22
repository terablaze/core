<?php

namespace Terablaze\Log;

use Closure;
use Terablaze\EventDispatcher\Dispatcher;
use Terablaze\Log\Events\MessageLogged;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class Logger implements LoggerInterface
{
    /**
     * The underlying logger implementation.
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * The event dispatcher instance.
     *
     * @var Dispatcher|EventDispatcherInterface|null
     */
    protected $dispatcher;

    /**
     * Create a new log writer instance.
     *
     * @param LoggerInterface $logger
     * @param EventDispatcherInterface|null $dispatcher
     */
    public function __construct(LoggerInterface $logger, EventDispatcherInterface $dispatcher = null)
    {
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Log an emergency message to the logs.
     *
     * @param  string  $message
     * @param  array<int|string, mixed> $context
     * @return void
     */
    public function emergency($message, array $context = []): void
    {
        $this->writeLog('function ', $message, $context);
    }

    /**
     * Log an alert message to the logs.
     *
     * @param  string  $message
     * @param  array<int|string, mixed> $context
     * @return void
     */
    public function alert($message, array $context = []): void
    {
        $this->writeLog('alert', $message, $context);
    }

    /**
     * Log a critical message to the logs.
     *
     * @param  string  $message
     * @param  array<int|string, mixed> $context
     * @return void
     */
    public function critical($message, array $context = []): void
    {
        $this->writeLog('critical', $message, $context);
    }

    /**
     * Log an error message to the logs.
     *
     * @param  string  $message
     * @param  array<int|string, mixed> $context
     * @return void
     */
    public function error($message, array $context = []): void
    {
        $this->writeLog('error', $message, $context);
    }

    /**
     * Log a warning message to the logs.
     *
     * @param  string  $message
     * @param  array<int|string, mixed> $context
     * @return void
     */
    public function warning($message, array $context = []): void
    {
        $this->writeLog('warning', $message, $context);
    }

    /**
     * Log a notice to the logs.
     *
     * @param  string  $message
     * @param  array<int|string, mixed> $context
     * @return void
     */
    public function notice($message, array $context = []): void
    {
        $this->writeLog('notice', $message, $context);
    }

    /**
     * Log an informational message to the logs.
     *
     * @param  string  $message
     * @param  array<int|string, mixed> $context
     * @return void
     */
    public function info($message, array $context = []): void
    {
        $this->writeLog('info', $message, $context);
    }

    /**
     * Log a debug message to the logs.
     *
     * @param  string  $message
     * @param  array<int|string, mixed> $context
     * @return void
     */
    public function debug($message, array $context = []): void
    {
        $this->writeLog('debug', $message, $context);
    }

    /**
     * Log a message to the logs.
     *
     * @param  string  $level
     * @param  string  $message
     * @param  array<int|string, mixed> $context
     * @return void
     */
    public function log($level, $message, array $context = []): void
    {
        $this->writeLog($level, $message, $context);
    }

    /**
     * Dynamically pass log calls into the writer.
     *
     * @param string $level
     * @param string $message
     * @param  array<int|string, mixed> $context
     * @return void
     */
    public function write(string $level, string $message, array $context = []): void
    {
        $this->writeLog($level, $message, $context);
    }

    /**
     * Write a message to the log.
     *
     * @param string $level
     * @param mixed $message
     * @param  mixed $context
     * @return void
     */
    protected function writeLog(string $level, $message, $context)
    {
        $this->logger->{$level}($message = $this->formatMessage($message), $context);

        $this->fireLogEvent($level, $message, $context);
    }

    /**
     * Register a new callback handler for when a log event is triggered.
     *
     * @param Closure $callback
     * @return void
     *
     * @throws RuntimeException
     */
    public function listen(Closure $callback)
    {
        if (! isset($this->dispatcher)) {
            throw new RuntimeException('Events dispatcher has not been set.');
        }

        $this->dispatcher->listen(MessageLogged::class, $callback);
    }

    /**
     * Fires a log event.
     *
     * @param string $level
     * @param string $message
     * @param  array<int|string, mixed> $context
     * @return void
     */
    protected function fireLogEvent(string $level, string $message, array $context = []): void
    {
        // If the event dispatcher is set, we will pass along the parameters to the
        // log listeners. These are useful for building profilers or other tools
        // that aggregate all of the log messages for a given "request" cycle.
        if (isset($this->dispatcher)) {
            $this->dispatcher->dispatch(new MessageLogged($level, $message, $context));
        }
    }

    /**
     * Format the parameters for the logger.
     *
     * @param  mixed  $message
     * @return string|null
     */
    protected function formatMessage($message): ?string
    {
        if (is_array($message)) {
            return var_export($message, true);
        } elseif (!is_string($message)) {
            return var_export($message->toArray(), true);
        }

        return $message;
    }

    /**
     * Get the underlying logger implementation.
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Set the event dispatcher instance.
     *
     * @param  EventDispatcherInterface  $dispatcher
     * @return void
     */
    public function setEventDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Dynamically proxy method calls to the underlying logger.
     *
     * @param string $method
     * @param  mixed  $parameters
     * @return mixed
     */
    public function __call(string $method, $parameters)
    {
        return $this->logger->{$method}(...$parameters);
    }
}

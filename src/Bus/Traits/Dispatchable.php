<?php

namespace Terablaze\Bus\Traits;

use Closure;
use Terablaze\Bus\Dispatcher;
use Terablaze\Bus\Pending\PendingChain;
use Terablaze\Bus\Pending\PendingDispatch;
use Terablaze\Container\Container;
use Terablaze\Support\Fluent;

trait Dispatchable
{
    use GetDispatcher;

    /**
     * Dispatch the job with the given arguments.
     *
     * @return PendingDispatch
     */
    public static function dispatch(...$arguments)
    {
        return new PendingDispatch(new static(...$arguments));
    }

    /**
     * Dispatch the job with the given arguments if the given truth test passes.
     *
     * @param  bool|\Closure  $boolean
     * @param  mixed  ...$arguments
     * @return PendingDispatch|Fluent
     */
    public static function dispatchIf($boolean, ...$arguments)
    {
        if ($boolean instanceof Closure) {
            $dispatchable = new static(...$arguments);

            return value($boolean, $dispatchable)
                ? new PendingDispatch($dispatchable)
                : new Fluent;
        }

        return value($boolean)
            ? new PendingDispatch(new static(...$arguments))
            : new Fluent;
    }

    /**
     * Dispatch the job with the given arguments unless the given truth test passes.
     *
     * @param  bool|\Closure  $boolean
     * @param  mixed  ...$arguments
     * @return PendingDispatch|Fluent
     */
    public static function dispatchUnless($boolean, ...$arguments)
    {
        if ($boolean instanceof Closure) {
            $dispatchable = new static(...$arguments);

            return ! value($boolean, $dispatchable)
                ? new PendingDispatch($dispatchable)
                : new Fluent;
        }

        return ! value($boolean)
            ? new PendingDispatch(new static(...$arguments))
            : new Fluent;
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * Queueable jobs will be dispatched to the "sync" queue.
     *
     * @return mixed
     */
    public static function dispatchSync(...$arguments)
    {
        return static::getDispatcher()->dispatchSync(new static(...$arguments));
    }

    /**
     * Dispatch a command to its appropriate handler after the current process.
     *
     * @return mixed
     */
    public static function dispatchAfterResponse(...$arguments)
    {
        return static::getDispatcher()->dispatchAfterResponse(new static(...$arguments));
    }

    /**
     * Set the jobs that should run if this job is successful.
     *
     * @param  array  $chain
     * @return PendingChain
     */
    public static function withChain($chain)
    {
        return new PendingChain(static::class, $chain);
    }
}

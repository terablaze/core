<?php

use TeraBlaze\Profiler\DebugBar\TeraBlazeDebugbar;

if (!function_exists('debugbar')) {
    /**
     * Get the DebugBar instance
     *
     * @return TeraBlazeDebugbar
     */
    function debugbar(): TeraBlazeDebugbar
    {
        return container()->get(TeraBlazeDebugbar::class);
    }
}

if (!function_exists('debug')) {
    /**
     * Adds one or more messages to the MessagesCollector
     *
     * @param  mixed ...$value
     * @return void
     */
    function debug($value): void
    {
        $debugbar = debugbar();
        foreach (func_get_args() as $value) {
            $debugbar->addMessage($value, 'debug');
        }
    }
}

if (!function_exists('startMeasure')) {
    /**
     * Starts a measure
     *
     * @param string $name Internal name, used to stop the measure
     * @param string $label Public name
     */
    function startMeasure($name, $label = null)
    {
        debugbar()->startMeasure($name, $label);
    }
}

if (!function_exists('stopMeasure')) {
    /**
     * Stop a measure
     *
     * @param string $name Internal name, used to stop the measure
     */
    function stopMeasure($name)
    {
        debugbar()->stopMeasure($name);
    }
}

if (!function_exists('addMeasure')) {
    /**
     * Adds a measure
     *
     * @param string $label
     * @param float $start
     * @param float $end
     */
    function addMeasure($label, $start, $end)
    {
        debugbar()->addMeasure($label, $start, $end);
    }
}

if (!function_exists('measure')) {
    /**
     * Utility function to measure the execution of a Closure
     *
     * @param string $label
     * @param \Closure $closure
     */
    function measure($label, \Closure $closure)
    {
        debugbar()->measure($label, $closure);
    }
}

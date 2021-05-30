<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/16/2017
 * Time: 8:49 PM
 */

namespace TeraBlaze\Events;

/**
 * Class Events
 * @package TeraBlaze
 */
class Events
{
    private static $_callbacks = array();

    /**
     * Events constructor.
     * to ensure that this class is not instantiable
     */
    private function __construct()
    {
        // do nothing
    }

    /**
     * also to ensure that an instance of this class cannot be cloned
     */
    private function __clone()
    {
        // do nothing
    }

    /**
     * @param $type
     * @param $callback
     *
     * adds an event to the $_callback array
     */
    public static function add($type, $callback)
    {
        if (empty(self::$_callbacks[$type])) {
            self::$_callbacks[$type] = array();
        }

        self::$_callbacks[$type][] = $callback;
    }

    /**
     * @param string $type
     * @param array<int, mixed>|null $parameters
     *
     * fires the events in the $_callback array
     */
    public static function fire(string $type, ?array $parameters = null): void
    {
        if (!empty(self::$_callbacks[$type])) {
            foreach (self::$_callbacks[$type] as $callback) {
                call_user_func_array($callback, $parameters);
            }
        }
    }

    /**
     * @param $type
     * @param $callback
     *
     * removes an event from the $_callback array
     */
    public static function remove($type, $callback)
    {
        if (!empty(self::$_callbacks[$type])) {
            foreach (self::$_callbacks[$type] as $i => $found) {
                if ($callback == $found) {
                    unset(self::$_callbacks[$type][$i]);
                }
            }
        }
    }
}

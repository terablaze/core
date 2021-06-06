<?php

namespace TeraBlaze\Router\Route;

use TeraBlaze\ArrayMethods as ArrayMethods;
use TeraBlaze\Router\Router;

/**
 * Class Simple
 * @package TeraBlaze\Router\Route
 */
class Simple extends Route
{
    /**
     * @param string $url
     * @return bool
     */
    public function matches(string $url): bool
    {
        $url = trim($url, '/');
        $path = explode("#", $this->path);
        $pattern = $path[0];
        $path = explode("?", $pattern);
        $pattern = $path[0];

        preg_match_all("#" . Router::NAMED_ROUTE_MATCH . "|:([a-zA-Z0-9]+)#", $pattern, $keys);

        $keysToReplace = [];
        $keyPatterns = [];
        foreach ($keys[0] as $key) {
            $keysToReplace[] = "{$key}";
            $keyParts = explode(":", trim($key, "{}"));
            $keyPattern = $keyParts[1] ?? "any";
            $keyPatterns[] = in_array("#(:" . $keyPattern . ")#", Router::PATTERN_KEYS) ?
                ":" . $keyPattern : $keyPattern;
        }

        $pattern = str_replace($keysToReplace, $keyPatterns, $pattern);

        unset($keys);

        // get keys
        preg_match_all("#:([a-zA-Z0-9]+)#", $pattern, $keys);

        if (sizeof($keys) && sizeof($keys[0]) && sizeof($keys[1])) {
            $keys = $keys[1];
        } else {
            // no keys in the pattern, return a simple match
            return preg_match("#^{$pattern}$#", $url);
        }

        // normalize route pattern
        $pattern = preg_replace(Router::PATTERN_KEYS, Router::PATTERN_KEYS_REPLACEMENTS, $pattern);

        // check values
        preg_match_all("#^{$pattern}$#", $url, $values);

        if (sizeof($values) && sizeof($values[0]) && sizeof($values[1])) {
            // unset the matched url
            unset($values[0]);

            // values found, modify parameters and return
            //$derived = array_combine($keys, ArrayMethods::flatten($values));
            $derived = ArrayMethods::flatten($values);
            $this->parameters = array_merge($this->parameters, $derived);

            return true;
        }

        return false;
    }
}

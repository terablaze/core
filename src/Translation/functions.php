<?php

use Terablaze\Translation\Translator;

if (!function_exists('trans')) {
    /**
     * Translate the given message.
     *
     * @param string|null $key
     * @param array $replace
     * @param string|null $locale
     * @return Translator|string|array|null
     */
    function trans(?string $key = null, array $replace = [], ?string $locale = null)
    {
        if (is_null($key)) {
            return container()->get('translator');
        }

        return container()->get('translator')->get($key, $replace, $locale);
    }
}

if (!function_exists('transChoice')) {
    /**
     * Translates the given message based on a count.
     *
     * @param string $key
     * @param \Countable|int|array $number
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    function transChoice($key, $number, array $replace = [], $locale = null)
    {
        return container()->get('translator')->choice($key, $number, $replace, $locale);
    }
}

if (!function_exists('__')) {
    /**
     * Translate the given message.
     *
     * @param string|null $key
     * @param array $replace
     * @param string|null $locale
     * @return string|array|null
     */
    function __($key = null, $replace = [], $locale = null)
    {
        if (is_null($key)) {
            return $key;
        }

        return trans($key, $replace, $locale);
    }
}

if (!function_exists('___')) {
    /**
     * Translates the given message based on a count.
     *
     * @param $key
     * @param $number
     * @param array $replace
     * @param null $locale
     * @return array|string|Translator|null
     */
    function ___($key, $number, array $replace = [], $locale = null)
    {
        return transChoice($key, $number, $replace, $locale);
    }
}

<?php

namespace TeraBlaze\Translation;

use Countable;

interface TranslatorInterface
{
    /**
     * Get the translation for a given key.
     *
     * @param string $key
     * @param  array  $replace
     * @param string|null $locale
     * @return mixed
     */
    public function get(string $key, array $replace = [], ?string $locale = null);

    /**
     * Get a translation according to an integer value.
     *
     * @param string $key
     * @param  Countable|int|array  $number
     * @param  array  $replace
     * @param string|null $locale
     * @return string
     */
    public function choice(string $key, $number, array $replace = [], ?string $locale = null): string;

    /**
     * Get the default locale being used.
     *
     * @return string
     */
    public function getLocale(): string;

    /**
     * Set the default locale.
     *
     * @param string $locale
     * @return void
     */
    public function setLocale(string $locale);
}

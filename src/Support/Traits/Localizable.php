<?php

namespace Terablaze\Support\Traits;

use Terablaze\Container\Container;
use Terablaze\Support\Helpers;

trait Localizable
{
    /**
     * Run the callback with the given locale.
     *
     * @param  string  $locale
     * @param  \Closure  $callback
     * @return mixed
     */
    public function withLocale($locale, $callback)
    {
        if (! $locale) {
            return $callback();
        }

        $app = Container::getContainer();

        $original = Helpers::getCurrentLocale();

        try {
            Helpers::setConfig('app.locale', $locale);

            return $callback();
        } finally {
            Helpers::setConfig('app.locale', $original);
        }
    }
}

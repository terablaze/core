<?php

namespace Terablaze\Session\Traits;

use Terablaze\Support\Helpers;

trait SessionAwareTrait
{
    public function addFlash(string $key, $value, $hops = 1): void
    {
        Helpers::flash()->flash($key, $value, $hops);
    }

    public function addFlashNow(string $key, $value, int $hops = 1): void
    {
        Helpers::flash()->flashNow($key, $value, $hops);
    }

    public function getFlash(string $key, $default = null)
    {
        return Helpers::flash()->getFlash($key, $default);
    }
}

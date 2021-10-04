<?php

namespace TeraBlaze\Session\Traits;

trait SessionAwareTrait
{
    public function addFlash(string $key, $value, $hops = 1): void
    {
        flash()->flash($key, $value, $hops);
    }

    public function addFlashNow(string $key, $value, int $hops = 1): void
    {
        flash()->flashNow($key, $value, $hops);
    }

    public function getFlash(string $key, $default = null)
    {
        flash()->getFlash($key, $default);
    }
}
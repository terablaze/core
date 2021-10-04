<?php

namespace TeraBlaze\ErrorHandler;

use ReflectionException;
use TeraBlaze\Collection\ArrayCollection;
use TeraBlaze\Collection\Exceptions\TypeException;
use TeraBlaze\View\View;

class RegisterErrorViewPaths
{
    /**
     * Register the error view paths.
     *
     * @return void
     * @throws ReflectionException
     * @throws TypeException
     */
    public function __invoke()
    {
        $paths = (new ArrayCollection(getConfig('views.paths', [])))->map(function ($path) {
            return "{$path}/errors";
        })->push(__DIR__ . '/Resources/views')->all();
        View::addNamespacedPaths('errors', $paths);
    }
}

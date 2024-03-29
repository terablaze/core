<?php

namespace Terablaze\ErrorHandler;

use ReflectionException;
use Terablaze\Collection\ArrayCollection;
use Terablaze\Collection\Exceptions\TypeException;
use Terablaze\Support\Helpers;
use Terablaze\View\View;

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
        $paths = (new ArrayCollection(Helpers::getConfig('view.paths', [])))->map(function ($path) {
            return "{$path}/errors";
        })->push(__DIR__ . '/Resources/views')->all();
        View::addNamespacedPaths('errors', $paths);
    }
}

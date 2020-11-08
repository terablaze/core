<?php

use TeraBlaze\Container\Container;
use TeraBlaze\Router\Router;

if (!function_exists('path')) {
    function path(string $path = '', array $parameters = [])
    {
        $container = Container::getContainer();
        /** @var Router $router */
        $router = $container->get(Router::class);
        if (isset($router->getRoutes()[$path])) {
            $path = $router->getGenerator()->generate($path, $parameters);
        }
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $virtualLocation = $container->hasParameter('virtualLocation') ?
            rtrim($container->getParameter('virtualLocation'), '/\\') :
            preg_replace('#public/[\w-]*.php(.*)$#', '', $scriptName);
        return "{$virtualLocation}{$path}";
    }
}

if (!function_exists('asset')) {
    function asset($uri = '')
    {
        $container = Container::getContainer();
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $virtualLocation = $container->hasParameter('virtualLocation') ?
            rtrim($container->getParameter('virtualLocation'), '/\\') :
            preg_replace('#[\w-]*.php(.*)$#', '', $scriptName);
        return "{$virtualLocation}assets/{$uri}";
    }
}
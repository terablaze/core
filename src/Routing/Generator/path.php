<?php

use TeraBlaze\Container\Container;
use TeraBlaze\Routing\Generator\UrlGenerator;
use TeraBlaze\Routing\Router;

if (!function_exists('path')) {
    function path(string $path = '', array $parameters = [], int $referenceType = UrlGenerator::ABSOLUTE_PATH)
    {
        $container = Container::getContainer();
        /** @var Router $router */
        $router = $container->get(Router::class);

        return $router->getGenerator()->generate($path, $parameters, $referenceType);
    }
}
if (!function_exists('route')) {
    function route(string $path = '', array $parameters = [], int $referenceType = UrlGenerator::ABSOLUTE_PATH)
    {
        return path($path, $parameters, $referenceType);
    }
}

if (!function_exists('asset')) {
    function asset(string $uri = '', int $referenceType = UrlGenerator::ABSOLUTE_PATH)
    {
        $container = Container::getContainer();
        /** @var Router $router */
        $router = $container->get(Router::class);

        return $router->getGenerator()->generateAsset($uri, $referenceType);
    }
}

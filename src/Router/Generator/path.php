<?php

use TeraBlaze\Container\Container;
use TeraBlaze\Router\Generator\UrlGenerator;
use TeraBlaze\Router\Router;

if (!function_exists('path')) {
    function path(string $path = '', array $parameters = [], int $referenceType = UrlGenerator::ABSOLUTE_PATH)
    {
        $container = Container::getContainer();
        /** @var Router $router */
        $router = $container->get(Router::class);

        return $router->getGenerator()->generate($path, $parameters, $referenceType);
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

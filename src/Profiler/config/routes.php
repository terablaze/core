<?php

return [
    [
        '@group',
        '@prefix' => '_profiler/debugbar/',
        '@name_prefix' => 'profiler.debugbar.',
        '@routes' => [
            'openhandler' => [
                'pattern' => 'open',
                'controller' => \TeraBlaze\Profiler\Controllers\DebugBar\OpenHandlerController::class,
                'action' => 'handle',
                'expects_json' => true,
            ],
            'assets.css' => [
                'pattern' => 'css',
                'controller' => \TeraBlaze\Profiler\Controllers\DebugBar\AssetController::class,
                'action' => 'css',
            ],
            'assets.js' => [
                'pattern' => 'js',
                'controller' => \TeraBlaze\Profiler\Controllers\DebugBar\AssetController::class,
                'action' => 'js',
            ],
            'assets.fonts' => [
                'pattern' => 'fonts/{font:any}',
                'controller' => \TeraBlaze\Profiler\Controllers\DebugBar\AssetController::class,
                'action' => 'font',
            ],
        ],
    ],
];

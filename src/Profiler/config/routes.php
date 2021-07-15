<?php

return [
    [
        '@group',
        '@prefix' => '_profiler/debugbar/',
        '@name_prefix' => 'profiler.debugbar.',
        '@expects_json' => true,
        '@routes' => [
            'openhandler' => [
                'pattern' => 'open',
                'controller' => \TeraBlaze\Profiler\Controllers\Debugbar\OpenHandlerController::class,
                'action' => 'handle',
                'expects_json' => true,
            ],
        ],
    ],
];

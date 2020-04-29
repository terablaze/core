<?php

error_reporting(-1);
ini_set('display_errors', 1);


include_once __DIR__ . "/../../vendor/autoload.php";

use TeraBlaze\Container\Container;
use TeraBlaze\Container\Reference\ServiceReference;
use TeraBlaze\Container\Reference\ParameterReference;
use Tests\Container\BhutaniService;

$services = [
    'service.anthony' => [
        'class' => \Tests\Container\AnthonyService::class,
        'calls' => [
            [
                'method' => 'setChaftani',
                'arguments' => ['@service.chaftani'],
            ],
        ],
    ],
    'service.bhutani' => [
        'class' => BhutaniService::class,
        'arguments' => ['@service.anthony'],
    ],
    'service.chaftani' => [
        'class' => \Tests\Container\ChaftaniService::class,
        'arguments' => ['%some.parameter%', 'Bare string param']
    ]
];

$parameters = [
    'some' => [
        'parameter' => 'Some string parameter',
    ],
    'refff' => [
        'l2' => [
            'l3' => [
                'l4' => '%some.parameter%',
            ],
        ],
    ],
    'buthan' => [
        'a1' => [
            'a2' => [
                'a3' => '%refff.l2.l3.l4%'
            ]
        ]
    ]
];

$container = Container::createContainer($services, $parameters);

$as = $container->get('service.anthony');
dump($as);

$bs = $container->get('service.bhutani');
dump($bs);

$cs = $container->get('service.chaftani');
dump($cs);

$pr = $container->getParameter('refff.l2.l3.l4');
dump($pr);

$pr = $container->getParameter('buthan.a1.a2.a3');
dump($pr);

dump($container);
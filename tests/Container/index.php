<?php

error_reporting(-1);
ini_set('display_errors', 1);


include_once __DIR__ . "/../../vendor/autoload.php";

use TeraBlaze\Container\Container;
use Tests\TeraBlaze\Container\AutowireService;
use Tests\TeraBlaze\Container\BhutaniService;

$services = [
    'service.anthony' => [
        'class' => \Tests\TeraBlaze\Container\AnthonyService::class,
        'calls' => [
            [
                'method' => 'setChaftani',
                'arguments' => ['@service.chaftani'],
            ],
            [
                'method' => 'setChaftani2',
                'arguments' => ["tomtom"],
            ],
        ],
    ],
    'service.bhutani' => [
        'class' => BhutaniService::class,
        'arguments' => ['@service.anthony'],
    ],
    'service.chaftani' => [
        'class' => \Tests\TeraBlaze\Container\ChaftaniService::class,
        'arguments' => [
            'chaftaniParam' => ['%refff%'],
            'bareParam' => 'Bare string param'
        ]
    ],
    'service.dagaro' => [
        'class' => \Tests\TeraBlaze\Container\DagaroService::class,
    ],
];

$parameters = [
    'some' => [
        'parameter' => 'Some string parameter',
    ],
    'a' => [
        'b' => '%some.parameter%',
    ],

    'refff' => [
        'l2' => [
            'l3' => [
                'l4' => '%some.parameter%',
                'l42' => '@service.dagaro',
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

$date = new DateTime();

$container = Container::getContainer($services, $parameters);

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

$container->registerServiceInstance($date);

dump($container);

$container->registerService(AutowireService::class, ['class' => AutowireService::class]);
$as = $container->get(AutowireService::class);
dd($as);

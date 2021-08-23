<?php

use TeraBlaze\Routing\Generator\UrlGenerator;
use TeraBlaze\Routing\Router;

error_reporting(-1);
ini_set('display_errors', 1);


include_once __DIR__ . "/../../vendor/autoload.php";


$routes = [
    'home' => [
        'pattern' => 'home/{userId:num}/book/:alpha/:num',
        'controller' => 'App\Controller\Pages',
        'action' => 'home',
        'expects_json' => true,
    ],
    'general' => [
        'pattern' => 'general/{userId:num}/book/:alpha/:num',
        'controller' => 'App\Controller\Pages',
        'action' => 'general',
    ],
    'brigade' => [
        'pattern' => 'brigade/{userId:num}/book/:alpha/{ripana:num:My default\'s  val.,ue}',
        'controller' => 'App\Controller\Pages',
        'action' => 'brigade',
    ],
    'easy' => [
        'pattern' => 'url-easy',
        'controller' => 'App\Controller\Pages',
        'action' => 'easy',
    ],
    [
        '@group',
        '@prefix' => 'prefix-test/',
        '@name_prefix' => 'step2.',
        '@expects_json' => true,
        '@routes' => [
            'easy_prexy' => [
                'pattern' => 'url-easy-locato',
                'controller' => 'App\Controller\Pages',
                'action' => 'easy',
            ],
            [
                '@group',
                '@prefix' => 'prefix-test2/',
                '@name_prefix' => 'step3.',
                '@expects_json' => false,
                '@routes' => [
                    'easy_prexy' => [
                        'pattern' => 'url-easy-locato2',
                        'controller' => 'App\Controller\Pages',
                        'action' => 'easy',
                    ],
                ]
            ],
            'easy1_continued' => [
                'pattern' => '11-locato',
                'controller' => 'App\Controller\Pages',
                'action' => 'easy',
            ],
        ],
    ],
    'base_continued' => [
        'pattern' => 'base-locato',
        'controller' => 'App\Controller\Pages',
        'action' => 'easy',
    ],
];

dump($routes);

$container = \TeraBlaze\Container\Container::getContainer();

$container->registerService(Router::class, [Router::class]);
$router = $container->get(Router::class);

// add defined routes
$router->addRoutes($routes);

dump($router->getRoutes());
dump($router->getRoutes()['brigade']->matches('brigade/1/book/author/1'));
dump($router->getRoutes());

$urlGenerator = new UrlGenerator($router);
dump($urlGenerator->generate('brigade', [
    "userId" => 1,
    "babayaga" => "jognny",
    "johnny" => "depp",
], UrlGenerator::RELATIVE_PATH));

$container->registerServiceInstance($router);

$response = new \TeraBlaze\HttpBase\Response(json_encode(["tommy", "tommy"], 128));
$response = $response->withHeader('Set-Cookie', "tom tom");
$response->withHeader('Location', 'https://google.com');
$stream = $response->getBody();
$contents = $stream->getContents(); // returns all the contents
dump($contents);
$contents = $stream->getContents(); // empty string
dump($contents);
$stream->rewind(); // Seek to the beginning
dump($contents);
$contents = $stream->getContents(); // returns all the contents
dump($contents);
$request = \TeraBlaze\HttpBase\Request::createFromGlobals();
dump($request->getUri());

dump(asset('easy', 0), asset('easy', 1), asset('easy', 2), asset('easy', 3));

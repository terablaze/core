<?php

use TeraBlaze\Router\Generator\UrlGenerator;
use TeraBlaze\Router\Router;

error_reporting(-1);
ini_set('display_errors', 1);


include_once __DIR__ . "/../../vendor/autoload.php";


$routes = [
    'home' => [
        'pattern' => 'home/{userId:num}/book/:alpha/:num',
        'controller' => 'App\Controller\Pages',
        'action' => 'home',
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
];

dump($routes);

$container = \TeraBlaze\Container\Container::getContainer();

$router = new Router();

// add defined routes
if (!empty($routes) && is_array($routes)) {
    foreach ($routes as $name => $route) {
        $router->addRoute($name, new \TeraBlaze\Router\Route\Simple($route));
    }
}

dump($router->getRoutes());
dump($router->getRoutes()['brigade']->matches('brigade/1/book/author/1'));
dump($router->getRoutes());

$urlGenerator = new UrlGenerator($router->getRoutes());
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
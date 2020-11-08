<?php

declare(strict_types=1);

ob_start();

error_reporting(1);
ini_set('display_errors', "1");

use TeraBlaze\HttpBase\Core\Psr7\Factory\Psr17Factory;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Core\Psr7Server\ServerRequestCreator;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

include_once __DIR__ . "/../../vendor/autoload.php";

$psr17Factory = new Psr17Factory();

$creator = new ServerRequestCreator(
    $psr17Factory, // ServerRequestFactory
    $psr17Factory, // UriFactory
    $psr17Factory, // UploadedFileFactory
    $psr17Factory  // StreamFactory
);

/** @var Request $request */
$request = $creator->fromGlobals();

$kernel = new \Tests\TeraBlaze\Http\Kernel();
$response = $kernel->handle($request);

(new SapiEmitter())->emit($response);

flush();
ob_flush();
ob_end_clean();

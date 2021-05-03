<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;

require_once __DIR__ . "/../../vendor/autoload.php";

$kernel = new \Tests\TeraBlaze\Http\Kernel('dev', true);

$request = Request::createFromGlobals();

// dd($_SERVER, $_REQUEST, $request, parse_url($_SERVER['REQUEST_URI']));
dd($request, $request->getPathInfo());

/** @var Response */
$response = $kernel->handle($request);

$response->send();


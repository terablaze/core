<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;

require_once __DIR__ . "/../../vendor/autoload.php";

$kernel = new \Tests\TeraBlaze\Http\Kernel('dev', true);

$request = Request::createFromGlobals();

/** @var Response */
$response = $kernel->handle($request);

$response->send();


<?php

declare(strict_types=1);

use TeraBlaze\HttpBase\Request;

require_once __DIR__ . "/../../vendor/autoload.php";

$kernel = new \Tests\TeraBlaze\Http\Kernel('dev', true);

$request = Request::createFromGlobals();

$response = $kernel->handle($request);

$response->send();


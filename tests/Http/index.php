<?php

declare(strict_types=1);

use TeraBlaze\HttpBase\Request;
use Tests\TeraBlaze\Http\Kernel;

error_reporting(1);
ini_set('display_errors', "1");

include_once __DIR__ . "/../../vendor/autoload.php";

$request = Request::createFromGlobals();

$kernel = new Kernel();
$response = $kernel->handle($request);

$response->send();


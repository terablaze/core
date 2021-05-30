<?php

declare(strict_types=1);

use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;

require_once __DIR__ . "/../../vendor/autoload.php";

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(
        explode(',', $trustedProxies),
        Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST
    );
}

if ($trustedHosts = '^(localhost|example\.com)$' ?? false) {
    Request::setTrustedHosts([$trustedHosts]);
}

$kernel = new \Tests\TeraBlaze\Http\Kernel('dev', true);

/** @var Request $request */
$request = Request::createFromGlobals();

/** @var Response */
$response = $kernel->handle($request);

$response->send();

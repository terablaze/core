<?php

declare(strict_types=1);

namespace TeraBlaze\HttpBase\Core\Psr7\Factory;

use Psr\Http\Message\ServerRequestInterface;
use TeraBlaze\HttpBase\Request as TeraBlazeRequest;

class Psr17Factory extends \TeraBlaze\Psr7\Factory\Psr17Factory
{
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return new TeraBlazeRequest($method, $uri, [], null, '1.1', $serverParams);
    }
}

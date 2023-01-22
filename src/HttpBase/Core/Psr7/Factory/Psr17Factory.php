<?php

declare(strict_types=1);

namespace Terablaze\HttpBase\Core\Psr7\Factory;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Terablaze\HttpBase\Request as TerablazeRequest;
use Terablaze\HttpBase\Response as TerablazeResponse;

class Psr17Factory extends \Terablaze\Psr7\Factory\Psr17Factory
{
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return new TerablazeRequest($method, $uri, [], null, '1.1', $serverParams);
    }

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        if (2 > \func_num_args()) {
            // This will make the Response class to use a custom reasonPhrase
            $reasonPhrase = null;
        }

        return new TerablazeResponse(null, $code, [], '1.1', $reasonPhrase);
    }
}

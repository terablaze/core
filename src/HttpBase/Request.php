<?php

namespace TeraBlaze\HttpBase;

use Psr\Http\Message\ServerRequestInterface;
use TeraBlaze\HttpBase\Core\Psr7\Factory\Psr17Factory;
use TeraBlaze\Psr7\ServerRequest as Psr7ServerRequest;
use TeraBlaze\Psr7Server\ServerRequestCreator;

class Request extends Psr7ServerRequest
{
    /**
     * Commented out code allows previous output before this emit display without raising exception
     * @see Response::send() for the end part counterpart
     * @return bool
     */
    public static function createFromGlobals(): ServerRequestInterface
    {
//        ob_start();
        $psr17Factory = new Psr17Factory();

        $creator = new ServerRequestCreator(
            $psr17Factory, // ServerRequestFactory
            $psr17Factory, // UriFactory
            $psr17Factory, // UploadedFileFactory
            $psr17Factory  // StreamFactory
        );

        return $creator->fromGlobals();
    }
}
<?php

namespace TeraBlaze\Core\Kernel\Events;

use TeraBlaze\Core\Kernel\HttpKernelInterface;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;

/**
 * Allows to filter a Response object.
 *
 * You can call getResponse() to retrieve the current response. With
 * setResponse() you can set a new response that will be returned to the
 * browser.
 */
final class ResponseEvent extends KernelEvent
{
    private $response;

    public function __construct(HttpKernelInterface $kernel, Request $request, Response $response)
    {
        parent::__construct($kernel, $request);

        $this->setResponse($response);
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }
}

<?php

namespace TeraBlaze\Routing\Events;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use TeraBlaze\EventDispatcher\Event;
use TeraBlaze\Routing\Router;

/**
 * Base class for events thrown in the Routing component.
 */
abstract class RouterEvent extends Event
{
    private Router $router;
    private ServerRequestInterface $request;
    private ?ResponseInterface $response = null;

    /**
     * RouterEvent constructor.
     * @param Router $router
     * @param ServerRequestInterface $request
     */
    public function __construct(Router $router, ServerRequestInterface $request)
    {
        $this->router = $router;
        $this->request = $request;
    }

    /**
     * Returns the Routing instance
     *
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Returns the request the being processed.
     *
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Returns the response object.
     *
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Sets a response and stops event propagation.
     */
    public function setResponse(ResponseInterface $response): void
    {
        $this->response = $response;

        $this->stopPropagation();
    }

    /**
     * Returns whether a response was set.
     *
     * @return bool Whether a response was set
     */
    public function hasResponse(): bool
    {
        return null !== $this->response;
    }
}

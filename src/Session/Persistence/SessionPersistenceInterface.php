<?php

namespace TeraBlaze\Session\Persistence;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TeraBlaze\Session\SessionInterface;

interface SessionPersistenceInterface
{
    /**
     * Generate a session data instance based on the request.
     */
    public function initializeSessionFromRequest(ServerRequestInterface $request): SessionInterface;

    /**
     * Persist the session data instance.
     *
     * Persists the session data, returning a response instance with any
     * artifacts required to return to the client.
     */
    public function persistSession(SessionInterface $session, ResponseInterface $response): ResponseInterface;
}

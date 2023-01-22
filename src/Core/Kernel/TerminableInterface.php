<?php

namespace Terablaze\Core\Kernel;

use Terablaze\HttpBase\Request;
use Terablaze\HttpBase\Response;

/**
 * Terminable extends the Kernel request/response cycle with dispatching a post
 * response event after sending the response and before shutting down the kernel.
 */
interface TerminableInterface
{
    /**
     * Terminates a request/response cycle.
     *
     * Should be called after sending the response and before shutting down the kernel.
     */
    public function terminate(Request $request, Response $response): void;
}

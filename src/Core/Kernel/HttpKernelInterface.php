<?php

namespace Terablaze\Core\Kernel;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Terablaze\HttpBase\Request;
use Terablaze\HttpBase\Response;

interface HttpKernelInterface
{
    /**
     * Handles a Request to convert it to a Response.
     *
     * When $catch is true, the implementation must catch all exceptions
     * and do its best to convert them to a Response instance.
     *
     * @param Request $request
     *
     * @param bool $catch Whether to catch exceptions or not
     *
     * @return ResponseInterface A Response instance
     *
     * @throws Exception When an Exception occurs during processing
     */
    public function handle(Request $request, bool $catch = true): ResponseInterface;
}

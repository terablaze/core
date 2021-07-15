<?php

namespace TeraBlaze\Profiler\DebugBar;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TeraBlaze\HttpBase\Request;

class DebugbarMiddleware implements MiddlewareInterface
{
    /**
     * @var TeraBlazeDebugbar|null The debugbar
     */
    private $debugbar;

    /**
     * The URIs that should be excluded.
     *
     * @var string[]
     */
    protected $exclude = [];

    /**
     * Set the debug bar.
     * @param TeraBlazeDebugbar|null $debugbar
     */
    public function __construct(TeraBlazeDebugbar $debugbar = null)
    {
        $this->debugbar = $debugbar ?: new TeraBlazeDebugbar();
        $this->exclude = getConfig('profiler.debugbar.exclude') ?: [];
    }

    /**
     * Process a server request and return a response.
     * @param Request|ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->debugbar->isEnabled() || $this->inExcludeArray($request)) {
            return $handler->handle($request);
        }

        $this->debugbar->boot();
        $response = $handler->handle($request);

        return $this->debugbar->modifyResponse($request, $response);
    }



    public function getDebugBar(): TeraBlazeDebugbar
    {
        return $this->debugbar;
    }


    /**
     * Determine if the request has a URI that should be ignored.
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function inExcludeArray($request)
    {
        foreach ($this->exclude as $exclude) {
            if ($exclude !== '/') {
                $exclude = trim($exclude, '/');
            }

            if ($request->is($exclude)) {
                return true;
            }
        }

        return false;
    }
}

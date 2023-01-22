<?php

namespace Terablaze\Profiler\DebugBar;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Terablaze\HttpBase\Request;

class DebugbarMiddleware implements MiddlewareInterface
{
    /**
     * @var TerablazeDebugbar|null The debugbar
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
     * @param TerablazeDebugbar|null $debugbar
     */
    public function __construct(TerablazeDebugbar $debugbar = null)
    {
        $this->debugbar = $debugbar ?: new TerablazeDebugbar();
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



    public function getDebugBar(): TerablazeDebugbar
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

<?php

namespace TeraBlaze\Profiler\Controllers\DebugBar;

use DebugBar\OpenHandler;
use TeraBlaze\HttpBase\Response;

class OpenHandlerController extends BaseController
{
    public function handle()
    {
        $openHandler = new OpenHandler($this->debugbar);
        $data = $openHandler->handle(null, false, false);

        return new Response(
            $data,
            200,
            [
                'Content-Type' => 'application/json'
            ]
        );
    }
}

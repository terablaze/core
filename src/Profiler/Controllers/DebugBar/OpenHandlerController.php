<?php

namespace Terablaze\Profiler\Controllers\DebugBar;

use DebugBar\OpenHandler;
use Terablaze\HttpBase\Response;

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

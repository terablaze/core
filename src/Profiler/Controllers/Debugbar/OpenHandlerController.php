<?php

namespace TeraBlaze\Profiler\Controllers\Debugbar;

use DebugBar\OpenHandler;
use TeraBlaze\Controller\Controller;
use TeraBlaze\HttpBase\JsonResponse;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\Profiler\DebugBar\TeraBlazeDebugbar;

class OpenHandlerController extends Controller
{
    public $debugbar;

    public function __construct(TeraBlazeDebugbar $debugbar)
    {
        $this->debugbar = $debugbar;
    }

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

    /**
     * Return Clockwork output
     *
     * @param $id
     * @return mixed
     * @throws \DebugBar\DebugBarException
     */
    public function clockwork($id)
    {
        $request = [
            'op' => 'get',
            'id' => $id,
        ];

        $openHandler = new OpenHandler($this->debugbar);
        $data = $openHandler->handle($request, false, false);

        // Convert to Clockwork
        $converter = new Converter();
        $output = $converter->convert(json_decode($data, true));

        return response()->json($output);
    }
}

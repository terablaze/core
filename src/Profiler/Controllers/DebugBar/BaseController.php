<?php

namespace TeraBlaze\Profiler\Controllers\DebugBar;

use TeraBlaze\Controller\AbstractController;
use TeraBlaze\Profiler\DebugBar\TeraBlazeDebugbar;

class BaseController extends AbstractController
{
    public $debugbar;

    public function __construct(TeraBlazeDebugbar $debugbar)
    {
        $this->debugbar = $debugbar;

        if (request()->hasFlash()) {
            request()->getFlash()->reflash();
        }
    }
}

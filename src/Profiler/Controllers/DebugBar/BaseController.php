<?php

namespace Terablaze\Profiler\Controllers\DebugBar;

use Terablaze\Controller\AbstractController;
use Terablaze\Profiler\DebugBar\TerablazeDebugbar;

class BaseController extends AbstractController
{
    public $debugbar;

    public function __construct(TerablazeDebugbar $debugbar)
    {
        $this->debugbar = $debugbar;

        if (request()->hasFlash()) {
            request()->getFlash()->reflash();
        }
    }
}

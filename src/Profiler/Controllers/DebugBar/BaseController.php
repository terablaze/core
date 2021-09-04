<?php

namespace TeraBlaze\Profiler\Controllers\DebugBar;

use TeraBlaze\Controller\Controller;
use TeraBlaze\Profiler\DebugBar\TeraBlazeDebugbar;

class BaseController extends Controller
{
    public $debugbar;

    public function __construct(TeraBlazeDebugbar $debugbar)
    {
        $this->debugbar = $debugbar;
    }
}

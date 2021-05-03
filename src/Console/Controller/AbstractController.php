<?php

namespace TeraBlaze\Console\Controller;

use TeraBlaze\Console\Application;

abstract class AbstractController
{
    protected $app;

    abstract public function run($argv);

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    protected function getApp()
    {
        return $this->app;
    }
}

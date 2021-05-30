<?php

namespace TeraBlaze\Console\Controller;

class HelpController extends AbstractController
{
    public function run($argv)
    {
        $name = isset($argv[2]) ? $argv[2] : "World";
        $this->getApp()->getPrinter()->display("Hello $name!!!");
    }
}

<?php

namespace Tests\Container;

class ChaftaniService
{
    public $cp;
    public $bp;

    public function __construct(string $chaftaniParam, string $bareParam)
    {
        $this->cp = $chaftaniParam;
        $this->bp = $bareParam;
    }
}
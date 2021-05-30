<?php

namespace Tests\TeraBlaze\Container;

class ChaftaniService
{
    public $cp;
    public $bp;

    public function __construct($chaftaniParam, string $bareParam)
    {
        $this->cp = $chaftaniParam;
        $this->bp = $bareParam;

        dd($chaftaniParam);
    }
}

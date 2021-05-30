<?php

namespace Tests\TeraBlaze\Container;

class AutowireService
{
    public $as;
    public $bs;
    public $ss;
    public $brs;

    public function __construct(AnthonyService $anthonyService, BhutaniService $bhutaniService, string $someString = "Ff", string $bareString = "Tungba")
    {
        $this->as = $anthonyService;
        $this->bs = $bhutaniService;
        $this->ss = $someString;
        $this->brs = $bareString;
    }
}

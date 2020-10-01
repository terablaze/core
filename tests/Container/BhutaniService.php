<?php

namespace Tests\Container;

class BhutaniService
{
    public $as;

    public function __construct(AnthonyService $anthonyService)
    {
        $this->as = $anthonyService;
    }
}

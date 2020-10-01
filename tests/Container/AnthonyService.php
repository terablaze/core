<?php

namespace Tests\Container;

class AnthonyService
{
    public $chaftani;

    public function sayAnthony()
    {
        echo "Anthony";
    }

    public function dumpAnthony()
    {
        dump($this);
    }

    public function setChaftani(ChaftaniService $chaftaniService)
    {
        $this->chaftani = $chaftaniService;
    }
}

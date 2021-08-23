<?php

namespace Tests\TeraBlaze\Container;

class AnthonyService
{
    public $chaftani;
    public string $chaftani2;

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

    public function setChaftani2(string $cheff)
    {
        $this->chaftani2 = $cheff;
    }
}

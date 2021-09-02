<?php

namespace TeraBlaze\View\Engine;

use TeraBlaze\View\View;

trait HasManagerTrait
{
    protected View $manager;

    public function setManager(View $manager): self
    {
        $this->manager = $manager;
        return $this;
    }

    public function getManager(): View
    {
        return $this->manager;
    }
}

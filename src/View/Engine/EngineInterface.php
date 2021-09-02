<?php

namespace TeraBlaze\View\Engine;

use TeraBlaze\View\View;
use TeraBlaze\View\Template;

interface EngineInterface
{
    public function render(Template $template): string;
    public function setManager(View $manager): self;
}

<?php

namespace Terablaze\View\Engine;

use Terablaze\View\View;
use Terablaze\View\Template;

interface EngineInterface
{
    public function render(Template $template): string;
    public function setManager(View $manager): self;
}

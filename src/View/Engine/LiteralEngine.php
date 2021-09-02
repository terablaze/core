<?php

namespace TeraBlaze\View\Engine;

use TeraBlaze\View\Template;

class LiteralEngine implements EngineInterface
{
    use HasManagerTrait;

    public function render(Template $view): string
    {
        return file_get_contents($view->path);
    }
}

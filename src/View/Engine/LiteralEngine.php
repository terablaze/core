<?php

namespace Terablaze\View\Engine;

use Terablaze\View\Template;

class LiteralEngine implements EngineInterface
{
    use HasManagerTrait;

    public function render(Template $view): string
    {
        return file_get_contents($view->path);
    }
}

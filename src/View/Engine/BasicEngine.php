<?php

namespace Terablaze\View\Engine;

use Terablaze\View\Engine\HasManagerTrait;
use Terablaze\View\Template;

class BasicEngine implements EngineInterface
{
    use HasManagerTrait;

    public function render(Template $view): string
    {
        $contents = file_get_contents($view->path);

        foreach ($view->data as $key => $value) {
            $contents = str_replace(
                '{' . $key . '}',
                '<?= $' . $key . '?>',
                $contents
            );
        }

        return $contents;
    }
}

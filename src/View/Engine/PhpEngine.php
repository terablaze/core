<?php

namespace Terablaze\View\Engine;

use Terablaze\View\Template;

class PhpEngine implements EngineInterface
{
    use HasManagerTrait;

    protected $layouts = [];

    public function render(Template $view): string
    {
        extract($view->getData());

        ob_start();
        include($view->getPath());
        $contents = ob_get_contents();
        ob_end_clean();

        if ($layout = $this->layouts[$view->getPath()] ?? null) {
            $contentsWithLayout = $this->getManager()->render($layout, array_merge(
                $view->getData(),
                ['contents' => $contents],
            ));

            return $contentsWithLayout;
        }

        return $contents;
    }

    protected function extends(string $template): self
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $this->layouts[realpath($backtrace[0]['file'])] = $template;
        return $this;
    }

    public function __call(string $name, $values)
    {
        return $this->manager->useMacro($name, ...$values);
    }
}

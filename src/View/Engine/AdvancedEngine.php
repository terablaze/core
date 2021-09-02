<?php

namespace TeraBlaze\View\Engine;

use TeraBlaze\View\Exception\TemplateNotFoundException;
use TeraBlaze\View\Template;

class AdvancedEngine implements EngineInterface
{
    use HasManagerTrait;

    /** @var string[] $layouts */
    protected array $layouts = [];

    /**
     * @param Template $template
     * @return string
     * @throws TemplateNotFoundException
     */
    public function render(Template $template): string
    {
        $cachedFile = $this->cache($template);
        $templateData = $template->getData();
        extract($templateData);
        ob_start();
        include($cachedFile);
        $contents = ob_get_contents();
        ob_end_clean();

        return (string)$contents;
    }

    public function cache(Template $template): string
    {
        $hash = md5($template->getPath());
        $cachedFile = normalizeDir($this->getManager()->getCachePath() . DIRECTORY_SEPARATOR . "$hash.php");

        if (
            !$this->getManager()->shouldCache() ||
            !file_exists($cachedFile) ||
            filemtime($template->getPath()) > filemtime($cachedFile)
        ) {
            $content = $this->compile($template->getPath());
            file_put_contents($cachedFile, '<?php class_exists(\'' . __CLASS__ . '\') or exit; ?>' . PHP_EOL . $content);
        }

        return $cachedFile;
    }

    protected function compile(string $templateFile): string
    {
        $template = (string)file_get_contents($templateFile);

        // replace `@extends` with `$this->extends`
        $template = preg_replace_callback('#@extends\(((?<=\().*(?=\)))\)#', function($matches) {
            return '<?php $this->extends(' . $matches[1] . '); ?>';
        }, $template);

        // replace `@if` with `if(...):`
        $template = preg_replace_callback('#@if\(((?<=\().*(?=\)))\)#', function($matches) {
            return '<?php if(' . $matches[1] . '): ?>';
        }, $template);

        // replace `@endif` with `endif;`
        $template = preg_replace_callback('#@endif#', function($matches) {
            return '<?php endif; ?>';
        }, $template);

        // replace `@foreach` with `foreach(...):`
        $template = preg_replace_callback('#@foreach\(((?<=\().*(?=\)))\)#', function($matches) {
            return '<?php foreach(' . $matches[1] . '): ?>';
        }, $template);

        // replace `@endforeach` with `endforeach;`
        $template = preg_replace_callback('#@endforeach#', function($matches) {
            return '<?php endforeach; ?>';
        }, $template);

        // replace `@[anything](...)` with `$this->[anything](...)`
        $template = preg_replace_callback('#\s+@([^(]+)\(((?<=\().*(?=\)))\)#', function($matches) {
            return '<?php $this->' . $matches[1] . '(' . $matches[2] . '); ?>';
        }, $template);

        // replace `{{ ... }}` with `print $this->escape(...)`
        $template = preg_replace_callback('#\{\{([^}]*)\}\}#', function($matches) {
            return '<?php print $this->escape(' . $matches[1] . '); ?>';
        }, $template);

        // replace `{!! ... !!}` with `print ...`
        $template = preg_replace_callback('#\{!!([^}]+)!!\}#', function($matches) {
            return '<?php print ' . $matches[1] . '; ?>';
        }, $template);

        return $template;
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

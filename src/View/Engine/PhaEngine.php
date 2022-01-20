<?php

namespace TeraBlaze\View\Engine;

use TeraBlaze\Support\StringMethods;
use TeraBlaze\View\Template;

class PhaEngine implements EngineInterface
{
    use HasManagerTrait;

    /** @var string[] $blocks */
    protected array $blocks = [];

    protected array $specials = [
        "{{" => "_DOUBLE_OPENING_BRACE",
        "}}" => "_DOUBLE_CLOSING_BRACE",
        "{" => "_OPENING_BRACE",
        "}" => "_CLOSING_BRACE",
        "%" => "_PERCENTAGE",
    ];

    private string $placeholder;

    public function __construct()
    {
        $this->placeholder = StringMethods::random();
    }

    /**
     * @param Template $template
     * @return string
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
        $cachedDir = normalizeDir(
            $this->getManager()->getCachePath() .
            DIRECTORY_SEPARATOR .
            $template->namespace
        );
        makeDir($cachedDir);
        $cachedFile = normalizeDir($cachedDir . DIRECTORY_SEPARATOR . "$hash.php");

        if (
            !$this->getManager()->shouldCache() ||
            !file_exists($cachedFile) ||
            filemtime($template->getPath()) > filemtime($cachedFile)
        ) {
            $content = $this->compile($template->getPath());
            file_put_contents(
                $cachedFile,
                '<?php class_exists(\'' . __CLASS__ . '\') or exit; ?>' . PHP_EOL . $content
            );
        }

        return $cachedFile;
    }

    protected function compile(string $templateFile): string
    {
        $code = $this->compileCode($this->includeFiles($templateFile));
        return str_replace(
            array_map(fn($special) => $this->placeholder . $special, array_values($this->specials)),
            array_keys($this->specials),
            $code
        );
    }

    protected function includeFiles(string $templateFile): string
    {
        $code = file_get_contents($templateFile);
        return $this->compileIncludes($code);
    }

    protected function compileIncludes(string $code): string
    {
        $code = str_replace(
            array_map(fn($special) => "\\" . $special, array_keys($this->specials)),
            array_map(fn($special) => $this->placeholder . $special, array_values($this->specials)),
            $code
        );
        preg_match_all('#{% ?(extends) ?\'?(.*?)\'? ?%}#i', (string)$code, $matches, PREG_SET_ORDER);
        foreach ($matches as $value) {
            $extended = $this->getManager()->includeFile($value[2]);
            $extended = $this->compileIncludes($extended);
            $code = str_replace($value[0], $extended, (string)$code);
        }
        preg_match_all('#{% ?(include) ?\'?(.*?)\'? ?%}#i', (string)$code, $matches, PREG_SET_ORDER);
        foreach ($matches as $value) {
            $included = $this->getManager()->includeFile($value[2]);
            $included = $this->compileIncludes($included);
            $code = str_replace($value[0], $included, (string)$code);
        }
        return preg_replace('#{% ?(extends|include) ?\'?(.*?)\'? ?%}#i', '', (string)$code);
    }

    protected function compileCode(string $code): string
    {
        $code = $this->compileComments($code);
        $code = $this->compileBlock($code);
        $code = $this->compileYield($code);
        $code = $this->compileEscapedEchos($code);
        $code = $this->compileRawEchos($code);
        $code = $this->compileIfs($code);
        $code = $this->compileLoops($code);
        $code = $this->compilePHP($code);
        return $code;
    }

    public function compileBlock($code)
    {
        preg_match_all('#{% ?block ?(.*?) ?%}(.*?){% ?endblock ?%}#is', $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $value) {
            if (!array_key_exists($value[1], $this->blocks)) {
                $this->blocks[$value[1]] = '';
            }
            if (strpos($value[2], '@parent') === false) {
                $this->blocks[$value[1]] = $value[2];
            } else {
                $this->blocks[$value[1]] = str_replace('@parent', $this->blocks[$value[1]], $value[2]);
            }
            $code = str_replace($value[0], '', $code);
        }
        return $code;
    }

    public function compileYield($code)
    {
        foreach ($this->blocks as $block => $value) {
            $value = addcslashes($value, '\\$');
            $code = preg_replace('#{% ?yield ?' . $block . ' ?%}#', $value, $code);
        }
        $code = preg_replace('#{% ?yield ?(.*?) ?%}#i', '', $code);
        return $code;
    }

    public function compileRawEchos($code)
    {
        $code = preg_replace('#\{!!\s*(.+?)\s*\!!}#is', '<?php echo $1 ?>', $code);
        return preg_replace('#\{\s*(.+?)\s*\}#is', '<?php echo $1 ?>', $code);
    }

    public function compileEscapedEchos($code)
    {
        return preg_replace('#\{{\s*(.+?)\s*\}}#is', '<?php echo htmlentities($1, ENT_QUOTES, \'UTF-8\') ?>', $code);
    }

    public function compileIfs($code)
    {
        // replace `{% if %}` with `if(...):`
        $code = preg_replace_callback('#{% ?if ?\(((?<=\().*(?=\)))\) ?%}#', function ($matches) {
            return '<?php if(' . $matches[1] . '): ?>';
        }, $code);

        // replace `{% elseif %}` with `elseif(...):`
        $code = preg_replace_callback('#{% ?elseif ?\(((?<=\().*(?=\)))\) ?%}#', function ($matches) {
            return '<?php elseif(' . $matches[1] . '): ?>';
        }, $code);

        // replace `{% else %}` with `else:`
        $code = preg_replace_callback('#{% ?else ?%}#', function ($matches) {
            return '<?php else: ?>';
        }, $code);

        // replace `{% endif %}` with `endif;`
        $code = preg_replace_callback('#{% ?endif ?%}#', function ($matches) {
            return '<?php endif; ?>';
        }, $code);

        return $code;
    }

    public function compileLoops($code)
    {
        // replace `{% foreach %}` with `foreach(...):`
        $code = preg_replace_callback('#{% ?foreach ?\(((?<=\().*(?=\)))\) ?%}#', function ($matches) {
            return '<?php foreach(' . $matches[1] . '): ?>';
        }, $code);

        // replace `{% endforeach %}` with `endforeach;`
        $code = preg_replace_callback('#{% ?endforeach ?%}#', function ($matches) {
            return '<?php endforeach; ?>';
        }, $code);

        // replace `{% for %}` with `for(...):`
        $code = preg_replace_callback('#{% ?for ?\(((?<=\().*(?=\)))\) ?%}#', function ($matches) {
            return '<?php for(' . $matches[1] . '): ?>';
        }, $code);

        // replace `{% endfor %}` with `endfor;`
        $code = preg_replace_callback('#{% ?endfor ?%}#', function ($matches) {
            return '<?php endfor; ?>';
        }, $code);

        // replace `{% while %}` with `while(...):`
        $code = preg_replace_callback('#{% ?while ?\(((?<=\().*(?=\)))\) ?%}#', function ($matches) {
            return '<?php while(' . $matches[1] . '): ?>';
        }, $code);

        // replace `{% endwhile %}` with `endwhile;`
        $code = preg_replace_callback('#{% ?endwhile ?%}#', function ($matches) {
            return '<?php endwhile; ?>';
        }, $code);

        return $code;
    }

    public function compilePHP($code)
    {
        return preg_replace('~\{%\s*(.+?)\s*\%}~is', '<?php $1 ?>', $code);
    }

    public function compileComments($code)
    {
        return preg_replace('~\{#\s*(.+?)\s*\#}~is', '', $code);
    }

    public function __call(string $name, $values)
    {
        return $this->manager->useMacro($name, ...$values);
    }
}

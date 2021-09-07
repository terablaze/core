<?php

namespace TeraBlaze\Profiler\DebugBar;

use DebugBar\DebugBar;
use DebugBar\JavascriptRenderer as BaseJavascriptRenderer;

/**
 * {@inheritdoc}
 */
class JavascriptRenderer extends BaseJavascriptRenderer
{
    // Use XHR handler by default, instead of jQuery
    protected $ajaxHandlerBindToJquery = false;
    protected $ajaxHandlerBindToXHR = true;

    public function __construct(DebugBar $debugBar, $baseUrl = null, $basePath = null)
    {
        parent::__construct($debugBar, $baseUrl, $basePath);

        $this->cssVendors['fontawesome'] = __DIR__ . '/Resources/vendor/font-awesome/style.css';
        $this->cssFiles['mails'] = kernel()->getProjectDir() . '/vendor/maximebf/debugbar/src/DebugBar/Resources/widgets/mails/widget.css';
        $this->jsFiles['mails'] = kernel()->getProjectDir() . '/vendor/maximebf/debugbar/src/DebugBar/Resources/widgets/mails/widget.js';
        $this->cssFiles['sqlqueries'] = kernel()->getProjectDir() . '/vendor/maximebf/debugbar/src/DebugBar/Resources/widgets/sqlqueries/widget.css';
        $this->jsFiles['sqlqueries'] = kernel()->getProjectDir() . '/vendor/maximebf/debugbar/src/DebugBar/Resources/widgets/sqlqueries/widget.js';
        $this->cssFiles['templates'] = kernel()->getProjectDir() . '/vendor/maximebf/debugbar/src/DebugBar/Resources/widgets/templates/widget.css';
        $this->jsFiles['templates'] = kernel()->getProjectDir() . '/vendor/maximebf/debugbar/src/DebugBar/Resources/widgets/templates/widget.js';
    }

    /**
     * {@inheritdoc}
     */
    public function renderHead()
    {
        $cssRoute = route('profiler.debugbar.assets.css', [
            'v' => $this->getModifiedTime('css')
        ]);

        $jsRoute = route('profiler.debugbar.assets.js', [
            'v' => $this->getModifiedTime('js')
        ]);

        $cssRoute = preg_replace('/\Ahttps?:/', '', $cssRoute);
        $jsRoute  = preg_replace('/\Ahttps?:/', '', $jsRoute);

        $html  = "<link rel='stylesheet' type='text/css' property='stylesheet' href='{$cssRoute}'>";
        $html .= "<script type='text/javascript' src='{$jsRoute}'></script>";

        if ($this->isJqueryNoConflictEnabled()) {
            $html .= '<script type="text/javascript">jQuery.noConflict(true);</script>' . "\n";
        }

        $html .= $this->getInlineHtml();


        return $html;
    }

    protected function getInlineHtml()
    {
        $html = '';

        foreach (['head', 'css', 'js'] as $asset) {
            foreach ($this->getAssets('inline_' . $asset) as $item) {
                $html .= $item . "\n";
            }
        }

        return $html;
    }
    /**
     * Get the last modified time of any assets.
     *
     * @param string $type 'js' or 'css'
     * @return int
     */
    protected function getModifiedTime($type)
    {
        $files = $this->getAssets($type);

        $latest = 0;
        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime > $latest) {
                $latest = $mtime;
            }
        }
        return $latest;
    }

    /**
     * Return assets as a string
     *
     * @param string $type 'js' or 'css'
     * @return string
     */
    public function dumpAssetsToString($type)
    {
        $files = $this->getAssets($type);

        $content = '';
        foreach ($files as $file) {
            $content .= file_get_contents($file) . "\n";
        }

        return $content;
    }

    /**
     * Makes a URI relative to another
     *
     * @param string|array $uri
     * @param string $root
     * @return string|string[]
     */
    protected function makeUriRelativeTo($uri, $root)
    {
        if (!$root) {
            return $uri;
        }

        if (is_array($uri)) {
            $uris = [];
            foreach ($uri as $u) {
                $uris[] = $this->makeUriRelativeTo($u, $root);
            }
            return $uris;
        }

        if (substr($uri, 0, 1) === '/' || preg_match('/^([a-zA-Z]+:\/\/|[a-zA-Z]:\/|[a-zA-Z]:\\\)/', $uri)) {
            return $uri;
        }
        return rtrim($root, '/') . "/$uri";
    }
}

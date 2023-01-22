<?php

namespace Terablaze\Profiler\DebugBar\DataCollectors;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Terablaze\Profiler\DebugBar\DataFormatter\SimpleFormatter;
use Terablaze\View\Template;
use Terablaze\Support\StringMethods;

class ViewCollector extends DataCollector implements Renderable, AssetProvider
{
    protected $templates = [];
    protected $collect_data;
    protected $name;

    /**
     * Create a ViewCollector
     *
     * @param bool $collectData Collects view data when tru
     */
    public function __construct($collectData = true)
    {
        $this->setDataFormatter(new SimpleFormatter());
        $this->collect_data = $collectData;
        $this->name = 'views';
        $this->templates = [];
    }

    public function getName()
    {
        return 'views';
    }

    public function getWidgets()
    {
        return [
            'views' => [
                'icon' => 'leaf',
                'widget' => 'PhpDebugBar.Widgets.TemplatesWidget',
                'map' => 'views',
                'default' => '{}'
            ],
            'views:badge' => [
                'map' => 'views.nb_templates',
                'default' => 0
            ]
        ];
    }

    function getAssets()
    {
        return array(
            'css' => 'widgets/templates/widget.css',
            'js' => 'widgets/templates/widget.js'
        );
    }

    /**
     * Add a View instance to the Collector
     *
     * @param Template $view
     */
    public function addView(Template $view)
    {
        $name = $view->getName();
        $path = $view->getPath();

        if (!is_object($path)) {
            if ($path) {
                $path = ltrim(str_replace(baseDir(), '', realpath($path)), '/');
            }

            if (StringMethods::contains($path, '.pha')) {
                $type = 'pha';
            } else {
                $type = pathinfo($path, PATHINFO_EXTENSION);
            }
        } else {
            $type = get_class($view);
            $path = '';
        }

        if (!$this->collect_data) {
            $params = array_keys($view->getData());
        } else {
            $data = [];
            foreach ($view->getData() as $key => $value) {
                $data[$key] = $this->getDataFormatter()->formatVar($value);
            }
            $params = $data;
        }

        $template = [
            'name' => $path ? sprintf('%s (%s)', $name, $path) : $name,
            'param_count' => count($params),
            'params' => $params,
            'type' => $type,
        ];

        if ($this->getXdebugLink($path)) {
            $template['xdebug_link'] = $this->getXdebugLink(realpath($view->getPath()));
        }

        $this->templates[] = $template;
    }

    public function collect()
    {
        $templates = $this->templates;

        return [
            'nb_templates' => count($templates),
            'templates' => $templates,
        ];
    }
}

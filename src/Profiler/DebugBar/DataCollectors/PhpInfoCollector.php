<?php

namespace TeraBlaze\Profiler\DebugBar\DataCollectors;

use DebugBar\DataCollector\PhpInfoCollector as DebugBarPhpInfoCollector;

class PhpInfoCollector extends DebugBarPhpInfoCollector
{
    /**
     * @inheritDoc
     */
    public function getWidgets()
    {
        return tap(parent::getWidgets(), function (&$widgets) {
            dataSet($widgets, 'php_version.tooltip', 'PHP Version');
        });
    }
}

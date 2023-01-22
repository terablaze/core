<?php

namespace Terablaze\Profiler\DebugBar\DataCollectors;

use DebugBar\DataCollector\PhpInfoCollector as DebugBarPhpInfoCollector;
use Terablaze\Support\Helpers;

class PhpInfoCollector extends DebugBarPhpInfoCollector
{
    /**
     * @inheritDoc
     */
    public function getWidgets()
    {
        return tap(parent::getWidgets(), function (&$widgets) {
            Helpers::dataSet($widgets, 'php_version.tooltip', 'PHP Version');
        });
    }
}

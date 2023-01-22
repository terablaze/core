<?php

namespace Terablaze\Profiler\DebugBar\DataCollectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\Renderable;
use DebugBar\DebugBar;
use Terablaze\Core\Kernel\Kernel;
use Terablaze\Core\Kernel\KernelInterface;

/**
 * Class TerablazeCollector
 *
 * @package Terablaze\Profiler\DebugBar\DataCollectors
 * @author Tomiwa Ibiwoye <tomiwa@teraboxx.com>
 */
class TerablazeCollector extends DataCollector implements DataCollectorInterface, Renderable
{
    private KernelInterface $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Called by the DebugBar when data needs to be collected
     *
     * @return array Collected data
     */
    public function collect()
    {
        return [
            'version' => Kernel::TERABLAZE_VERSION,
            'environment' => $this->getEnvironmentInformation(),
            'locale' => $this->getLocale(),
        ];
    }

    /**
     * Returns the unique name of the collector
     *
     * @return string
     */
    public function getName()
    {
        return 'terablaze';
    }

    /**
     * Returns a hash where keys are control names and their values
     * an array of options as defined in {@see \DebugBar\JavascriptRenderer::addControl()}
     *
     * @return array
     */
    public function getWidgets()
    {
        return [
            'version' => [
                'icon' => 'tag',
                'tooltip' => 'Terablaze Version',
                'map' => 'terablaze.version',
                'default' => '',
            ],
            'environment' => [
                'icon' => 'desktop',
                'tooltip' => 'Environment',
                'map' => 'terablaze.environment',
                'default' => '',
            ],
            'locale' => [
                'icon' => 'flag',
                'tooltip' => 'Current locale',
                'map' => 'terablaze.locale',
                'default' => '',
            ],
        ];
    }

    private function getEnvironmentInformation()
    {
        return $this->kernel->getEnvironment();
    }

    private function getLocale()
    {
        return getCurrentLocale();
    }
}

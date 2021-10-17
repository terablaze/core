<?php

namespace TeraBlaze\Profiler\DebugBar\DataCollectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\Renderable;
use TeraBlaze\Session\SessionInterface;

class SessionCollector extends DataCollector implements DataCollectorInterface, Renderable
{
    /** @var SessionInterface $session */
    protected $session;

    /**
     * Create a new SessionCollector
     *
     * @param SessionInterface $session
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function collect()
    {
        $data = [];
        foreach ($this->session->toArray() as $key => $value) {
            $data[$key] = is_string($value) ? $value : $this->getDataFormatter()->formatVar($value);
        }
        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'session';
    }

    /**
     * {@inheritDoc}
     */
    public function getWidgets()
    {
        return [
            "session" => [
                "icon" => "archive",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "session",
                "default" => "{}"
            ]
        ];
    }
}

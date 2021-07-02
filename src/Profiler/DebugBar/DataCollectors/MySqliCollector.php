<?php

namespace TeraBlaze\Profiler\DebugBar\DataCollectors;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use TeraBlaze\Ripana\Logging\QueryLogger;

class MySqliCollector extends DataCollector implements Renderable, AssetProvider
{
    /** @var QueryLogger $queryLogger */
    protected $queryLogger;

    public $collectorId;

    public function __construct(QueryLogger $queryLogger, ?string $collectorId = '')
    {
        $this->queryLogger = $queryLogger;
        $this->collectorId = $collectorId;
    }

    public function collect()
    {
        $queries = array();
        $totalExecTime = 0;
        foreach ($this->queryLogger->queries as $query) {
            $queries[] = array(
                'sql' => $query['sql'],
                'duration' => $query['executionTime'],
                'duration_str' => $this->getDataFormatter()->formatDuration($query['executionTime']),
                'row_count' => $query['affectedRows'],
            );
            $totalExecTime += $query['executionTime'];
        }

        return array(
            'nb_statements' => count($queries),
            'accumulated_duration' => $totalExecTime,
            'accumulated_duration_str' => $this->formatDuration($totalExecTime),
            'statements' => $queries
        );
    }

    public function getName()
    {
        return 'Ripana(database)' . (empty($this->collectorId) ? '' : ".{$this->collectorId}");
    }

    public function getWidgets()
    {
        return array(
            $this->getName() => array(
                "icon" => "arrow-right",
                "widget" => "PhpDebugBar.Widgets.SQLQueriesWidget",
                "map" => $this->getName(),
                "default" => "[]"
            ),
            $this->getName() . ":badge" => array(
                "map" => $this->getName() . ".nb_statements",
                "default" => 0
            )
        );
    }

    public function getAssets()
    {
        return array(
            'css' => 'widgets/sqlqueries/widget.css',
            'js' => 'widgets/sqlqueries/widget.js'
        );
    }
}

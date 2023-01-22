<?php

namespace Terablaze\Profiler\DebugBar\DataCollectors\Database;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\Renderable;
use DebugBar\DataCollector\TimeDataCollector;
use Terablaze\Database\Logging\QueryLogger;

class QueryCollector extends DataCollector implements Renderable, AssetProvider
{
    /** @var QueryLogger[] $queryLoggers */
    protected array $queryLoggers;

    protected ?TimeDataCollector $timeCollector = null;

    protected bool $renderSqlWithParams = false;

    protected string $sqlQuotationChar = '<>';

    public function __construct(?QueryLogger $queryLogger = null, ?DataCollectorInterface $timeCollector = null)
    {
        $this->timeCollector = $timeCollector;
        if ($queryLogger !== null) {
            $this->addLogger($queryLogger, 'default');
        }
    }

    /**
     * Renders the SQL of traced statements with params embeded
     *
     * @param boolean $enabled
     * @param string $quotationChar
     */
    public function setRenderSqlWithParams(bool $enabled = true, string $quotationChar = '<>'): void
    {
        $this->renderSqlWithParams = $enabled;
        $this->sqlQuotationChar = $quotationChar;
    }

    /**
     * @return bool
     */
    public function isSqlRenderedWithParams(): bool
    {
        return $this->renderSqlWithParams;
    }

    /**
     * @return string
     */
    public function getSqlQuotationChar(): string
    {
        return $this->sqlQuotationChar;
    }

    /**
     * Adds a new PDO instance to be collector
     *
     * @param QueryLogger $queryLogger
     * @param string $name Optional connection name
     */
    public function addLogger(QueryLogger $queryLogger, $name = null)
    {
        if ($name === null) {
            $name = spl_object_hash($queryLogger);
        }
        $this->queryLoggers[$name] = $queryLogger;
    }

    public function collect()
    {
        $data = array(
            'nb_statements' => 0,
            'nb_failed_statements' => 0,
            'accumulated_duration' => 0,
            'memory_usage' => 0,
            'peak_memory_usage' => 0,
            'statements' => []
        );

        foreach ($this->queryLoggers as $name => $queryLogger) {
            $queryLoggerData = $this->collectQueryLogger($queryLogger, $this->timeCollector, $name);
            $data['nb_statements'] += $queryLoggerData['nb_statements'];
            $data['nb_failed_statements'] += $queryLoggerData['nb_failed_statements'];
            $data['accumulated_duration'] += $queryLoggerData['accumulated_duration'];
            $data['memory_usage'] += $queryLoggerData['memory_usage'];
            $data['peak_memory_usage'] = max($data['peak_memory_usage'], $queryLoggerData['peak_memory_usage']);
            $data['statements'] = array_merge(
                $data['statements'],
                array_map(
                    function ($s) use ($name) {
                        $s['connection'] = $name;
                        return $s;
                    },
                    $queryLoggerData['statements']
                )
            );
        }

        $data['accumulated_duration_str'] = $this->getDataFormatter()->formatDuration($data['accumulated_duration']);
        $data['memory_usage_str'] = $this->getDataFormatter()->formatBytes($data['memory_usage']);
        $data['peak_memory_usage_str'] = $this->getDataFormatter()->formatBytes($data['peak_memory_usage']);

        return $data;
    }

    public function collectQueryLogger(
        QueryLogger $queryLogger,
        ?TimeDataCollector $timeCollector = null,
        $connectionName = null
    ) {
        if (empty($connectionName) || $connectionName == 'default') {
            $connectionName = 'database';
        } else {
            $connectionName = 'database ' . $connectionName;
        }
        $queries = [];
        $totalExecTime = 0;
        $totalMemoryUsage = 0;
        $peakMemoryUsage = 0;
        $failedQueries = [];
        foreach ($queryLogger->queries as $query) {
            $queries[] = array(
                'sql' =>
                    $this->renderSqlWithParams ? $query->getSqlWithParams($this->sqlQuotationChar) : $query->getSql(),
                'row_count' => $query->getRowCount(),
                'stmt_id' => $query->getPreparedId(),
                'prepared_stmt' => $query->getSql(),
                'params' => (object)$query->getParameters(),
                'duration' => $query->getDuration(),
                'duration_str' => $this->getDataFormatter()->formatDuration($query->getDuration()),
                'memory' => $query->getMemoryUsage(),
                'memory_str' => $this->getDataFormatter()->formatBytes($query->getMemoryUsage()),
                'end_memory' => $query->getEndMemory(),
                'end_memory_str' => $this->getDataFormatter()->formatBytes($query->getEndMemory()),
                'is_success' => $query->isSuccess(),
                'error_code' => $query->getErrorCode(),
                'error_message' => $query->getErrorMessage()
            );
            $totalExecTime += $query->getDuration();
            $totalMemoryUsage += $query->getMemoryUsage();
            $peakMemoryUsage = max($peakMemoryUsage, $query->getMemoryUsage());
            if ($timeCollector !== null) {
                $timeCollector->addMeasure(
                    $query->getSql(),
                    $query->getStartTime(),
                    $query->getEndTime(),
                    [],
                    $connectionName
                );
            }
            if (! $query->isSuccess()) {
                $failedQueries[] = $query;
            }
        }

        return array(
            'nb_statements' => count($queries),
            'nb_failed_statements' => count($failedQueries),
            'accumulated_duration' => $totalExecTime,
            'accumulated_duration_str' => $this->getDataFormatter()->formatDuration($totalExecTime),
            'memory_usage' => $totalMemoryUsage,
            'memory_usage_str' => $this->getDataFormatter()->formatBytes($totalMemoryUsage),
            'peak_memory_usage' => $peakMemoryUsage,
            'peak_memory_usage_str' => $this->getDataFormatter()->formatBytes($peakMemoryUsage),
            'statements' => $queries
        );
    }

    public function getName()
    {
        return 'Database(query)';
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

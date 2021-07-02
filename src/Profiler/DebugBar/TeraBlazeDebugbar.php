<?php

namespace TeraBlaze\Profiler\DebugBar;

use DebugBar\Bridge\MonologCollector;
use DebugBar\Bridge\SwiftMailer\SwiftLogCollector;
use DebugBar\Bridge\SwiftMailer\SwiftMailCollector;
use DebugBar\DataCollector\ConfigCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\RequestDataCollector;
use DebugBar\DataFormatter\DataFormatter;
use DebugBar\Storage\PdoStorage;
use DebugBar\Storage\RedisStorage;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;
use TeraBlaze\Core\Kernel\Events\KernelEvent;
use TeraBlaze\Core\Kernel\Events\PostKernelBootEvent;
use TeraBlaze\Core\Kernel\Kernel;
use TeraBlaze\Core\Kernel\KernelInterface;
use TeraBlaze\EventDispatcher\Dispatcher;
use TeraBlaze\EventDispatcher\ListenerProvider;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\Profiler\DebugBar\DataCollectors\MySqliCollector;
use TeraBlaze\Profiler\Debugbar\DataCollectors\PhpInfoCollector;
use TeraBlaze\Profiler\DebugBar\DataCollectors\RequestCollector;
use TeraBlaze\Profiler\DebugBar\DataCollectors\RouteCollector;
use TeraBlaze\Profiler\DebugBar\DataCollectors\TeraBlazeCollector;
use TeraBlaze\Profiler\DebugBar\DataFormatter\QueryFormatter;
use TeraBlaze\Routing\Router;

class TeraBlazeDebugbar extends DebugBar
{
    /**
     * @var Container|ContainerInterface $container
     */
    protected $container;

    /**
     * Normalized TeraBlaze Version
     *
     * @var string
     */
    protected $version;

    /**
     * True when booted.
     *
     * @var bool
     */
    protected $booted = false;

    /** @var Dispatcher $dispatcher */
    protected $dispatcher;

    /** @var ListenerProvider $listener */
    protected $listener;
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var ?bool
     */
    protected ?bool $enabled = null;

    private LoggerInterface $logger;


    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container ?: Container::getContainer();
        $this->dispatcher = $this->container->get(EventDispatcherInterface::class);
        $this->listener = $this->container->get(ListenerProviderInterface::class);
//        $this->logger = $logger;
        $this->kernel = $this->container->get('kernel');
    }


    /**
     * Boot the debugbar (add collectors, renderer and listener)
     */
    public function boot(ServerRequestInterface $request, ResponseInterface $response)
    {
        if ($this->booted) {
            return;
        }

        /** @var TeraBlazeDebugbar $debugbar */
        $debugbar = $this;

        // Set custom error handler
        if ($this->container->getConfig('profiler.debugbar.error_handler', false)) {
            set_error_handler([$this, 'handleError']);
        }

        $this->selectStorage($this);

        if ($this->shouldCollect('phpinfo', true)) {
            $this->addCollector(new PhpInfoCollector());
        }

        if ($this->shouldCollect('messages', true)) {
            $this->addCollector(new MessagesCollector());
        }

        if ($this->shouldCollect('time', true)) {
            $this->addCollector(new TimeDataCollector());

            if (!$this->isLumen()) {
                $this->listener->addListener(PostKernelBootEvent::class,
                    function () use ($debugbar) {
                        $startTime = $this->kernel->getInitialRequest()->getServerParam('REQUEST_TIME_FLOAT');
                        if ($startTime) {
                            $debugbar['time']->addMeasure('Booting', $startTime, microtime(true));
                        }
                    }
                );
            }

            $debugbar->startMeasure('application', 'Application');
        }

        if ($this->shouldCollect('terablaze', false)) {
            $this->addCollector(new TeraBlazeCollector());
        }

        if ($this->shouldCollect('route')) {
            try {
                $this->addCollector(new RouteCollector(
                    $this->container->get(Router::class),
                    $this->container
                ));
            } catch (Exception $e) {
                $this->addThrowable(
                    new Exception(
                        'Cannot add RouteCollector to TeraBlaze Debugbar: ' . $e->getMessage(),
                        $e->getCode(),
                        $e
                    )
                );
            }
        }

        if ($this->shouldCollect('memory', true)) {
            $this->addCollector(new MemoryCollector());
        }

        if ($this->shouldCollect('exceptions', true)) {
            try {
                $exceptionCollector = new ExceptionsCollector();
                $exceptionCollector->setChainExceptions(
                    $this->container->getConfig('profiler.debugbar.options.exceptions.chain', true)
                );
                $this->addCollector($exceptionCollector);
            } catch (Exception $e) {
            }
        }

        if ($this->shouldCollect('request', true)) {
            $this->addCollector(new RequestCollector($request, $response));
        }

        if ($this->shouldCollect('ripana.query', true)) {
            $mysqliCollector = new MySqliCollector($this->container->get('ripana.database.connector')->getQueryLogger());
            $mysqliCollector->setDataFormatter(new QueryFormatter());
            $this->addCollector($mysqliCollector);
        }

//        if ($this->shouldCollect('log', true)) {
//            try {
//                if ($this->hasCollector('messages')) {
//                    $logger = new MessagesCollector('log');
//                    $this['messages']->aggregate($logger);
//                    $this->app['log']->listen(
//                        function ($level, $message = null, $context = null) use ($logger) {
//                            // Laravel 5.4 changed how the global log listeners are called. We must account for
//                            // the first argument being an "event object", where arguments are passed
//                            // via object properties, instead of individual arguments.
//                            if ($level instanceof \Illuminate\Log\Events\MessageLogged) {
//                                $message = $level->message;
//                                $context = $level->context;
//                                $level = $level->level;
//                            }
//
//                            try {
//                                $logMessage = (string)$message;
//                                if (mb_check_encoding($logMessage, 'UTF-8')) {
//                                    $logMessage .= (!empty($context) ? ' ' . json_encode($context) : '');
//                                } else {
//                                    $logMessage = "[INVALID UTF-8 DATA]";
//                                }
//                            } catch (Exception $e) {
//                                $logMessage = "[Exception: " . $e->getMessage() . "]";
//                            }
//                            $logger->addMessage(
//                                '[' . date('H:i:s') . '] ' . "LOG.$level: " . $logMessage,
//                                $level,
//                                false
//                            );
//                        }
//                    );
//                } else {
//                    $this->addCollector(new MonologCollector($this->app['log']->getMonolog()));
//                }
//            } catch (Exception $e) {
//                $this->addThrowable(
//                    new Exception(
//                        'Cannot add LogsCollector to TeraBlaze Debugbar: ' . $e->getMessage(), $e->getCode(), $e
//                    )
//                );
//            }
//        }
//
//        if ($this->shouldCollect('db', true) && isset($this->app['db'])) {
//            $db = $this->app['db'];
//            if ($debugbar->hasCollector('time') && $this->app['config']->get(
//                    'debugbar.options.db.timeline',
//                    false
//                )
//            ) {
//                $timeCollector = $debugbar->getCollector('time');
//            } else {
//                $timeCollector = null;
//            }
//            $queryCollector = new QueryCollector($timeCollector);
//
//            $queryCollector->setDataFormatter(new QueryFormatter());
//
//            if ($this->app['config']->get('debugbar.options.db.with_params')) {
//                $queryCollector->setRenderSqlWithParams(true);
//            }
//
//            if ($this->app['config']->get('debugbar.options.db.backtrace')) {
//                $middleware = !$this->is_lumen ? $this->app['router']->getMiddleware() : [];
//                $queryCollector->setFindSource(true, $middleware);
//            }
//
//            if ($this->app['config']->get('debugbar.options.db.backtrace_exclude_paths')) {
//                $excludePaths = $this->app['config']->get('debugbar.options.db.backtrace_exclude_paths');
//                $queryCollector->mergeBacktraceExcludePaths($excludePaths);
//            }
//
//            if ($this->app['config']->get('debugbar.options.db.explain.enabled')) {
//                $types = $this->app['config']->get('debugbar.options.db.explain.types');
//                $queryCollector->setExplainSource(true, $types);
//            }
//
//            if ($this->app['config']->get('debugbar.options.db.hints', true)) {
//                $queryCollector->setShowHints(true);
//            }
//
//            $this->addCollector($queryCollector);
//
//            try {
//                $db->listen(
//                    function ($query, $bindings = null, $time = null, $connectionName = null) use ($db, $queryCollector) {
//                        if (!$this->shouldCollect('db', true)) {
//                            return; // Issue 776 : We've turned off collecting after the listener was attached
//                        }
//                        // Laravel 5.2 changed the way some core events worked. We must account for
//                        // the first argument being an "event object", where arguments are passed
//                        // via object properties, instead of individual arguments.
//                        if ($query instanceof \Illuminate\Database\Events\QueryExecuted) {
//                            $bindings = $query->bindings;
//                            $time = $query->time;
//                            $connection = $query->connection;
//
//                            $query = $query->sql;
//                        } else {
//                            $connection = $db->connection($connectionName);
//                        }
//
//                        $queryCollector->addQuery((string)$query, $bindings, $time, $connection);
//                    }
//                );
//            } catch (Exception $e) {
//                $this->addThrowable(
//                    new Exception(
//                        'Cannot add listen to Queries for TeraBlaze Debugbar: ' . $e->getMessage(),
//                        $e->getCode(),
//                        $e
//                    )
//                );
//            }
//
//            try {
//                $db->getEventDispatcher()->listen(
//                    \Illuminate\Database\Events\TransactionBeginning::class,
//                    function ($transaction) use ($queryCollector) {
//                        $queryCollector->collectTransactionEvent('Begin Transaction', $transaction->connection);
//                    }
//                );
//
//                $db->getEventDispatcher()->listen(
//                    \Illuminate\Database\Events\TransactionCommitted::class,
//                    function ($transaction) use ($queryCollector) {
//                        $queryCollector->collectTransactionEvent('Commit Transaction', $transaction->connection);
//                    }
//                );
//
//                $db->getEventDispatcher()->listen(
//                    \Illuminate\Database\Events\TransactionRolledBack::class,
//                    function ($transaction) use ($queryCollector) {
//                        $queryCollector->collectTransactionEvent('Rollback Transaction', $transaction->connection);
//                    }
//                );
//
//                $db->getEventDispatcher()->listen(
//                    'connection.*.beganTransaction',
//                    function ($event, $params) use ($queryCollector) {
//                        $queryCollector->collectTransactionEvent('Begin Transaction', $params[0]);
//                    }
//                );
//
//                $db->getEventDispatcher()->listen(
//                    'connection.*.committed',
//                    function ($event, $params) use ($queryCollector) {
//                        $queryCollector->collectTransactionEvent('Commit Transaction', $params[0]);
//                    }
//                );
//
//                $db->getEventDispatcher()->listen(
//                    'connection.*.rollingBack',
//                    function ($event, $params) use ($queryCollector) {
//                        $queryCollector->collectTransactionEvent('Rollback Transaction', $params[0]);
//                    }
//                );
//            } catch (Exception $e) {
//                $this->addThrowable(
//                    new Exception(
//                        'Cannot add listen transactions to Queries for TeraBlaze Debugbar: ' . $e->getMessage(),
//                        $e->getCode(),
//                        $e
//                    )
//                );
//            }
//        }
//
//        if ($this->shouldCollect('models', true)) {
//            try {
//                $modelsCollector = $this->app->make('Barryvdh\Debugbar\DataCollector\ModelsCollector');
//                $this->addCollector($modelsCollector);
//            } catch (Exception $e) {
//                // No Models collector
//            }
//        }
//
//        if ($this->shouldCollect('livewire', true) && $this->app->bound('livewire')) {
//            try {
//                $livewireCollector = $this->app->make('Barryvdh\Debugbar\DataCollector\LivewireCollector');
//                $this->addCollector($livewireCollector);
//            } catch (Exception $e) {
//                $this->addThrowable(
//                    new Exception('Cannot add Livewire Collector: ' . $e->getMessage(), $e->getCode(), $e)
//                );
//            }
//        }
//
//        if ($this->shouldCollect('mail', true) && class_exists('Illuminate\Mail\MailServiceProvider')) {
//            try {
//                $mailer = $this->app['mailer']->getSwiftMailer();
//                $this->addCollector(new SwiftMailCollector($mailer));
//                if ($this->app['config']->get('debugbar.options.mail.full_log') && $this->hasCollector(
//                        'messages'
//                    )
//                ) {
//                    $this['messages']->aggregate(new SwiftLogCollector($mailer));
//                }
//            } catch (Exception $e) {
//                $this->addThrowable(
//                    new Exception(
//                        'Cannot add MailCollector to TeraBlaze Debugbar: ' . $e->getMessage(), $e->getCode(), $e
//                    )
//                );
//            }
//        }
//
//        if ($this->shouldCollect('logs', false)) {
//            try {
//                $file = $this->app['config']->get('debugbar.options.logs.file');
//                $this->addCollector(new LogsCollector($file));
//            } catch (Exception $e) {
//                $this->addThrowable(
//                    new Exception(
//                        'Cannot add LogsCollector to TeraBlaze Debugbar: ' . $e->getMessage(), $e->getCode(), $e
//                    )
//                );
//            }
//        }
//        if ($this->shouldCollect('files', false)) {
//            $this->addCollector(new FilesCollector($app));
//        }
//
//        if ($this->shouldCollect('auth', false)) {
//            try {
//                $guards = $this->app['config']->get('auth.guards', []);
//                $authCollector = new MultiAuthCollector($app['auth'], $guards);
//
//                $authCollector->setShowName(
//                    $this->app['config']->get('debugbar.options.auth.show_name')
//                );
//                $this->addCollector($authCollector);
//            } catch (Exception $e) {
//                $this->addThrowable(
//                    new Exception(
//                        'Cannot add AuthCollector to TeraBlaze Debugbar: ' . $e->getMessage(), $e->getCode(), $e
//                    )
//                );
//            }
//        }
//
//        if ($this->shouldCollect('gate', false)) {
//            try {
//                $gateCollector = $this->app->make('Barryvdh\Debugbar\DataCollector\GateCollector');
//                $this->addCollector($gateCollector);
//            } catch (Exception $e) {
//                // No Gate collector
//            }
//        }
//
//        if ($this->shouldCollect('cache', false) && isset($this->app['events'])) {
//            try {
//                $collectValues = $this->app['config']->get('debugbar.options.cache.values', true);
//                $startTime = $this->app['request']->server('REQUEST_TIME_FLOAT');
//                $cacheCollector = new CacheCollector($startTime, $collectValues);
//                $this->addCollector($cacheCollector);
//                $this->app['events']->subscribe($cacheCollector);
//
//            } catch (Exception $e) {
//                $this->addThrowable(
//                    new Exception(
//                        'Cannot add CacheCollector to TeraBlaze Debugbar: ' . $e->getMessage(),
//                        $e->getCode(),
//                        $e
//                    )
//                );
//            }
//        }

        $renderer = $this->getJavascriptRenderer();
        $renderer->setIncludeVendors($this->container->getConfig('profiler.debugbar.include_vendors', true));
        $renderer->setBindAjaxHandlerToFetch($this->container->getConfig('profiler.debugbar.capture_ajax', true));
        $renderer->setBindAjaxHandlerToXHR($this->container->getConfig('profiler.debugbar.capture_ajax', true));

        $this->booted = true;
    }

    public function shouldCollect($name, $default = true)
    {
        return $this->container->getConfig('profiler.debugbar.collectors.' . $name, $default);
    }

    /**
     * Adds a data collector
     *
     * @param DataCollectorInterface $collector
     * @return $this|TeraBlazeDebugbar
     * @throws \DebugBar\DebugBarException
     */
    public function addCollector(DataCollectorInterface $collector)
    {
        parent::addCollector($collector);

        if (method_exists($collector, 'useHtmlVarDumper')) {
            $collector->useHtmlVarDumper();
        }

        return $this;
    }

    /**
     * Handle silenced errors
     *
     * @param $level
     * @param $message
     * @param string $file
     * @param int $line
     * @param array $context
     * @throws \ErrorException
     */
    public function handleError($level, $message, $file = '', $line = 0, $context = [])
    {
        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        } else {
            $this->addMessage($message, 'deprecation');
        }
    }

    /**
     * Starts a measure
     *
     * @param string $name Internal name, used to stop the measure
     * @param string $label Public name
     */
    public function startMeasure($name, $label = null)
    {
        if ($this->hasCollector('time')) {
            /** @var \DebugBar\DataCollector\TimeDataCollector $collector */
            $collector = $this->getCollector('time');
            $collector->startMeasure($name, $label);
        }
    }

    /**
     * Stops a measure
     *
     * @param string $name
     */
    public function stopMeasure($name)
    {
        if ($this->hasCollector('time')) {
            /** @var \DebugBar\DataCollector\TimeDataCollector $collector */
            $collector = $this->getCollector('time');
            try {
                $collector->stopMeasure($name);
            } catch (Exception $e) {
                //  $this->addThrowable($e);
            }
        }
    }

    /**
     * Adds an exception to be profiled in the debug bar
     *
     * @param Exception $e
     * @throws \DebugBar\DebugBarException
     */
    public function addThrowable($e)
    {
        if ($this->hasCollector('exceptions')) {
            /** @var \DebugBar\DataCollector\ExceptionsCollector $collector */
            $collector = $this->getCollector('exceptions');
            $collector->addThrowable($e);
        }
    }

    /**
     * Modify the response and inject the debugbar (or data in headers)
     *
     * @param ServerRequestInterface|Request $request
     * @param ResponseInterface|Response $response
     * @return ResponseInterface|Response
     */
//    public function modifyResponse(ServerRequestInterface $request, ResponseInterface $response): Request
//    {
//        if (!$this->isEnabled() || $this->isDebugbarRequest()) {
//            return $response;
//        }
//
//        // Show the Http Response Exception in the Debugbar, when available
//        if (isset($response->exception)) {
//            $this->addThrowable($response->exception);
//        }
//
//        if ($this->shouldCollect('config', false)) {
//            try {
//                $configCollector = new ConfigCollector();
//                $configCollector->setData($this->container->getAllConfig());
//                $this->addCollector($configCollector);
//            } catch (Exception $e) {
//                $this->addThrowable(
//                    new Exception(
//                        'Cannot add ConfigCollector to TeraBlaze Debugbar: ' . $e->getMessage(),
//                        $e->getCode(),
//                        $e
//                    )
//                );
//            }
//        }
//
//        if ($response->isRedirection()) {
//            try {
//                $this->stackData();
//            } catch (Exception $e) {
//                $this->logger->error('Debugbar exception: ' . $e->getMessage());
//            }
//        } elseif (
//            $request->expectsJson() &&
//            $this->container->getConfig('profiler.debugbar.capture_ajax', true)
//        ) {
//            try {
//                $this->sendDataInHeaders(true);
//
//                if ($this->container->getConfig('profiler.debugbar.add_ajax_timing', false)) {
//                    $this->addServerTimingHeaders($response);
//                }
//
//            } catch (Exception $e) {
//                $this->logger->error('Debugbar exception: ' . $e->getMessage());
//            }
//        } elseif (
//            ($response->getHeaderLine('Content-Type') &&
//                strpos($response->getHeaderLine('Content-Type'), 'html') === false)
//            || $request->getRequestFormat() !== 'html'
//            || $response->getContent() === false
//            || $this->isJsonRequest($request)
//        ) {
//            try {
//                // Just collect + store data, don't inject it.
//                $this->collect();
//            } catch (Exception $e) {
//                $app['log']->error('Debugbar exception: ' . $e->getMessage());
//            }
//        } elseif ($app['config']->get('debugbar.inject', true)) {
//            try {
//                $this->injectDebugbar($response);
//            } catch (Exception $e) {
//                $app['log']->error('Debugbar exception: ' . $e->getMessage());
//            }
//        }
//
//
//        return $response;
//    }

    /**
     * Check if the Debugbar is enabled
     * @return boolean
     */
    public function isEnabled()
    {
        if ($this->enabled === null) {
            $configEnabled = $this->container->getConfig('profiler.debugbar.enabled');

            if ($configEnabled === null) {
                $configEnabled = $this->kernel->isDebug();
            }

            $this->enabled = (bool) $configEnabled;
        }

        return $this->enabled;
    }


    /**
     * Collects the data from the collectors
     *
     * @return array
     */
    public function collect()
    {
        /** @var Request $request */
        $request = $this->kernel->getInitialRequest();

        $this->data = [
            '__meta' => [
                'id' => $this->getCurrentRequestId(),
                'datetime' => date('Y-m-d H:i:s'),
                'utime' => microtime(true),
                'method' => $request->getMethod(),
                'uri' => $request->getRequestUri(),
                'ip' => $request->getClientIp()
            ]
        ];

        foreach ($this->collectors as $name => $collector) {
            $this->data[$name] = $collector->collect();
        }

        // Remove all invalid (non UTF-8) characters
        array_walk_recursive(
            $this->data,
            function (&$item) {
                if (is_string($item) && !mb_check_encoding($item, 'UTF-8')) {
                    $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
                }
            }
        );

        if ($this->storage !== null) {
            $this->storage->save($this->getCurrentRequestId(), $this->data);
        }

        return $this->data;
    }

    /**
     * Disable the Debugbar
     */
    public function disable()
    {
        $this->enabled = false;
    }

    /**
     * Adds a measure
     *
     * @param string $label
     * @param float $start
     * @param float $end
     */
    public function addMeasure($label, $start, $end)
    {
        if ($this->hasCollector('time')) {
            /** @var \DebugBar\DataCollector\TimeDataCollector $collector */
            $collector = $this->getCollector('time');
            $collector->addMeasure($label, $start, $end);
        }
    }

    /**
     * Utility function to measure the execution of a Closure
     *
     * @param string $label
     * @param \Closure $closure
     * @return mixed
     */
    public function measure($label, \Closure $closure)
    {
        if ($this->hasCollector('time')) {
            /** @var \DebugBar\DataCollector\TimeDataCollector $collector */
            $collector = $this->getCollector('time');
            $result = $collector->measure($label, $closure);
        } else {
            $result = $closure();
        }
        return $result;
    }


    /**
     * Magic calls for adding messages
     *
     * @param string $method
     * @param array $args
     * @return mixed|void
     */
    public function __call($method, $args)
    {
        $messageLevels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'];
        if (in_array($method, $messageLevels)) {
            foreach ($args as $arg) {
                $this->addMessage($arg, $method);
            }
        }
    }

    /**
     * Adds a message to the MessagesCollector
     *
     * A message can be anything from an object to a string
     *
     * @param mixed $message
     * @param string $label
     */
    public function addMessage($message, $label = 'info')
    {
        if ($this->hasCollector('messages')) {
            /** @var \DebugBar\DataCollector\MessagesCollector $collector */
            $collector = $this->getCollector('messages');
            $collector->addMessage($message, $label);
        }
    }

    /**
     * Check the version of Laravel
     *
     * @param string $version
     * @param string $operator (default: '>=')
     * @return boolean
     */
    protected function checkVersion($version, $operator = ">=")
    {
        return version_compare($this->version, $version, $operator);
    }

    /**
     * @param DebugBar $debugbar
     */
//    protected function selectStorage(DebugBar $debugbar)
//    {
//        $config = $this->app['config'];
//        if ($config->get('debugbar.storage.enabled')) {
//            $driver = $config->get('debugbar.storage.driver', 'file');
//
//            switch ($driver) {
//                case 'pdo':
//                    $connection = $config->get('debugbar.storage.connection');
//                    $table = $this->app['db']->getTablePrefix() . 'phpdebugbar';
//                    $pdo = $this->app['db']->connection($connection)->getPdo();
//                    $storage = new PdoStorage($pdo, $table);
//                    break;
//                case 'redis':
//                    $connection = $config->get('debugbar.storage.connection');
//                    $client = $this->app['redis']->connection($connection);
//                    if (is_a($client, 'Illuminate\Redis\Connections\Connection', false)) {
//                        $client = $client->client();
//                    }
//                    $storage = new RedisStorage($client);
//                    break;
//                case 'custom':
//                    $class = $config->get('debugbar.storage.provider');
//                    $storage = $this->app->make($class);
//                    break;
//                case 'file':
//                default:
//                    $path = $config->get('debugbar.storage.path');
//                    $storage = new FilesystemStorage($this->app['files'], $path);
//                    break;
//            }
//
//            $debugbar->setStorage($storage);
//        }
//    }
//
//    protected function addClockworkHeaders(Response $response)
//    {
//        $prefix = $this->app['config']->get('debugbar.route_prefix');
//        $response->headers->set('X-Clockwork-Id', $this->getCurrentRequestId(), true);
//        $response->headers->set('X-Clockwork-Version', 1, true);
//        $response->headers->set('X-Clockwork-Path', $prefix .'/clockwork/', true);
//    }
//
//    /**
//     * Add Server-Timing headers for the TimeData collector
//     *
//     * @see https://www.w3.org/TR/server-timing/
//     * @param Response $response
//     */
//    protected function addServerTimingHeaders(Response $response)
//    {
//        if ($this->hasCollector('time')) {
//            $collector = $this->getCollector('time');
//
//            $headers = [];
//            foreach ($collector->collect()['measures'] as $k => $m) {
//                $headers[] = sprintf('app;desc="%s";dur=%F', str_replace('"', "'", $m['label']), $m['duration'] * 1000);
//            }
//
//            $response->headers->set('Server-Timing', $headers, false);
//        }
//    }
}

<?php

namespace Terablaze\Profiler\DebugBar;

use DebugBar\Bridge\MonologCollector;
use DebugBar\DataCollector\ConfigCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DebugBarException;
use ErrorException;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Terablaze\Container\Container;
use Terablaze\Container\ContainerInterface;
use DebugBar\DebugBar;
use Terablaze\Core\Kernel\KernelInterface;
use Terablaze\EventDispatcher\Dispatcher;
use Terablaze\EventDispatcher\ListenerProvider;
use Terablaze\HttpBase\Core\Psr7\Factory\Psr17Factory;
use Terablaze\HttpBase\Request;
use Terablaze\HttpBase\Response;
use Terablaze\Log\Events\MessageLogged;
use Terablaze\Profiler\DebugBar\DataCollectors\Database\QueryCollector;
use Terablaze\Profiler\DebugBar\DataCollectors\EventCollector;
use Terablaze\Profiler\DebugBar\DataCollectors\PhpInfoCollector;
use Terablaze\Profiler\DebugBar\DataCollectors\RequestCollector;
use Terablaze\Profiler\DebugBar\DataCollectors\RouteCollector;
use Terablaze\Profiler\DebugBar\DataCollectors\SessionCollector;
use Terablaze\Profiler\DebugBar\DataCollectors\TerablazeCollector;
use Terablaze\Profiler\DebugBar\DataCollectors\ViewCollector;
use Terablaze\Profiler\DebugBar\DataFormatter\QueryFormatter;
use Terablaze\Profiler\DebugBar\Storage\FilesystemStorage;
use Terablaze\Routing\Router;
use Terablaze\Session\SessionInterface;
use Terablaze\View\Events\TemplateEvent;

class TerablazeDebugbar extends DebugBar
{
    /**
     * @var Container|ContainerInterface $container
     */
    protected $container;

    /**
     * Normalized Terablaze Version
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

    /** @var ListenerProvider $listenerProvider */
    protected $listenerProvider;
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var ?bool
     */
    protected ?bool $enabled = null;

    private LoggerInterface $logger;

    private static $mimes = [
        'css' => 'text/css',
        'js' => 'text/javascript',
    ];


    public function __construct(?ContainerInterface $container = null, ?LoggerInterface $logger = null)
    {
        $this->container = $container ?: Container::getContainer();
        $this->dispatcher = $this->container->get(EventDispatcherInterface::class);
        $this->listenerProvider = $this->container->get(ListenerProviderInterface::class);
        $this->logger = $logger ?? new NullLogger();
        $this->kernel = $this->container->get('kernel');
    }

    /**
     * Boot the debugbar (add collectors, renderer and listener)
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        /** @var TerablazeDebugbar $debugbar */
        $debugbar = $this;

        // Set custom error handler
        if (getConfig('profiler.debugbar.error_handler', false)) {
            set_error_handler([$debugbar, 'handleError']);
        }

        $this->selectStorage($debugbar);

        if ($this->shouldCollect('exceptions', true)) {
            try {
                $exceptionCollector = new ExceptionsCollector();
                $exceptionCollector->setChainExceptions(
                    getConfig('profiler.debugbar.options.exceptions.chain', true)
                );
                $this->addCollector($exceptionCollector);
            } catch (Exception $e) {
            }
        }

        if ($this->shouldCollect('phpinfo', true)) {
            $this->addCollector(new PhpInfoCollector());
        }

        if ($this->shouldCollect('messages', true)) {
            $this->addCollector(new MessagesCollector());
        }

        if ($this->shouldCollect('terablaze', false)) {
            $this->addCollector(new TerablazeCollector($this->kernel));
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
                        'Cannot add RouteCollector to Terablaze Debugbar: ' . $e->getMessage(),
                        $e->getCode(),
                        $e
                    )
                );
            }
        }

        if ($this->shouldCollect('memory', true)) {
            $this->addCollector(new MemoryCollector());
        }

        if ($this->shouldCollect('log', true)) {
            try {
                if ($this->hasCollector('messages')) {
                    $logger = new MessagesCollector('log');
                    $this['messages']->aggregate($logger);
                    $this->container->get('log')->listen(
                        function ($level, $message = null, $context = null) use ($logger) {
                            // Laravel 5.4 changed how the global log listeners are called. We must account for
                            // the first argument being an "event object", where arguments are passed
                            // via object properties, instead of individual arguments.
                            if ($level instanceof MessageLogged) {
                                $message = $level->message;
                                $context = $level->context;
                                $level = $level->level;
                            }

                            try {
                                $logMessage = (string)$message;
                                if (mb_check_encoding($logMessage, 'UTF-8')) {
                                    $logMessage .= (!empty($context) ? ' ' . json_encode($context) : '');
                                } else {
                                    $logMessage = "[INVALID UTF-8 DATA]";
                                }
                            } catch (Exception $e) {
                                $logMessage = "[Exception: " . $e->getMessage() . "]";
                            }
                            $logger->addMessage(
                                '[' . date('H:i:s') . '] ' . "LOG.$level: " . $logMessage,
                                $level,
                                false
                            );
                        }
                    );
                } else {
                    $this->addCollector(new MonologCollector($this->container->get('log')->driver()->getLogger()));
                }
            } catch (Exception $e) {
                $this->addThrowable(
                    new Exception(
                        'Cannot add LogsCollector to Terablaze Debugbar: ' . $e->getMessage(),
                        $e->getCode(),
                        $e
                    )
                );
            }
        }
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
//            if ($this->app['config']->get('profiler.debugbar.options.db.with_params')) {
//                $queryCollector->setRenderSqlWithParams(true);
//            }
//
//            if ($this->app['config']->get('profiler.debugbar.options.db.backtrace')) {
//                $middleware = !$this->is_lumen ? $this->app['router']->getMiddleware() : [];
//                $queryCollector->setFindSource(true, $middleware);
//            }
//
//            if ($this->app['config']->get('profiler.debugbar.options.db.backtrace_exclude_paths')) {
//                $excludePaths = $this->app['config']->get('profiler.debugbar.options.db.backtrace_exclude_paths');
//                $queryCollector->mergeBacktraceExcludePaths($excludePaths);
//            }
//
//            if ($this->app['config']->get('profiler.debugbar.options.db.explain.enabled')) {
//                $types = $this->app['config']->get('profiler.debugbar.options.db.explain.types');
//                $queryCollector->setExplainSource(true, $types);
//            }
//
//            if ($this->app['config']->get('profiler.debugbar.options.db.hints', true)) {
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
//                        'Cannot add listen to Queries for Terablaze Debugbar: ' . $e->getMessage(),
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
//                        'Cannot add listen transactions to Queries for Terablaze Debugbar: ' . $e->getMessage(),
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
//        if ($this->shouldCollect('mail', true) && class_exists('Terablaze\Mail\MailParcel')) {
//            try {
//                $mailer = $this->app['mailer']->getSwiftMailer();
//                $this->addCollector(new SwiftMailCollector($mailer));
//                if ($this->app['config']->get('profiler.debugbar.options.mail.full_log') && $this->hasCollector(
//                        'messages'
//                    )
//                ) {
//                    $this['messages']->aggregate(new SwiftLogCollector($mailer));
//                }
//            } catch (Exception $e) {
//                $this->addThrowable(
//                    new Exception(
//                        'Cannot add MailCollector to Terablaze Debugbar: ' . $e->getMessage(), $e->getCode(), $e
//                    )
//                );
//            }
//        }
//
//        if ($this->shouldCollect('logs', false)) {
//            try {
//                $file = $this->app['config']->get('profiler.debugbar.options.logs.file');
//                $this->addCollector(new LogsCollector($file));
//            } catch (Exception $e) {
//                $this->addThrowable(
//                    new Exception(
//                        'Cannot add LogsCollector to Terablaze Debugbar: ' . $e->getMessage(), $e->getCode(), $e
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
//                    $this->app['config']->get('profiler.debugbar.options.auth.show_name')
//                );
//                $this->addCollector($authCollector);
//            } catch (Exception $e) {
//                $this->addThrowable(
//                    new Exception(
//                        'Cannot add AuthCollector to Terablaze Debugbar: ' . $e->getMessage(), $e->getCode(), $e
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
//                $collectValues = $this->app['config']->get('profiler.debugbar.options.cache.values', true);
//                $startTime = $this->app['request']->server('REQUEST_TIME_FLOAT');
//                $cacheCollector = new CacheCollector($startTime, $collectValues);
//                $this->addCollector($cacheCollector);
//                $this->app['events']->subscribe($cacheCollector);
//
//            } catch (Exception $e) {
//                $this->addThrowable(
//                    new Exception(
//                        'Cannot add CacheCollector to Terablaze Debugbar: ' . $e->getMessage(),
//                        $e->getCode(),
//                        $e
//                    )
//                );
//            }
//        }

        if ($this->shouldCollect('events', false) && isset($this->dispatcher)) {
            try {
                $startTime = \request()->getServerParam('REQUEST_TIME_FLOAT');
                $eventCollector = new EventCollector($startTime);
                $this->addCollector($eventCollector);
                $eventCollector->subscribe($this->dispatcher);

            } catch (\Exception $e) {
                $this->addThrowable(
                    new Exception(
                        'Cannot add EventCollector to Terablaze Debugbar: ' . $e->getMessage(),
                        $e->getCode(),
                        $e
                    )
                );
            }
        }

        if ($this->shouldCollect('views', true) && isset($this->dispatcher)) {
            try {
                $collectData = getConfig('debugbar.options.views.data', true);
                $this->addCollector(new ViewCollector($collectData));
                $this->dispatcher->listen(
                    TemplateEvent::class,
                    function ($viewEvent) use ($debugbar) {
                        $debugbar['views']->addView($viewEvent->getTemplate());
                    }
                );
            } catch (\Exception $e) {
                $this->addThrowable(
                    new Exception(
                        'Cannot add ViewCollector to Terablaze Debugbar: ' . $e->getMessage(),
                        $e->getCode(),
                        $e
                    )
                );
            }
        }

        $renderer = $this->getJavascriptRenderer();
        $renderer->setIncludeVendors(getConfig('profiler.debugbar.include_vendors', true));
        $renderer->setBindAjaxHandlerToFetch(getConfig('profiler.debugbar.capture_ajax', true));
        $renderer->setBindAjaxHandlerToXHR(getConfig('profiler.debugbar.capture_ajax', true));

        $this->booted = true;
    }

    public function shouldCollect($name, $default = true)
    {
        return getConfig('profiler.debugbar.collectors.' . $name, $default);
    }

    /**
     * Adds a data collector
     *
     * @param DataCollectorInterface $collector
     * @return $this|TerablazeDebugbar
     * @throws DebugBarException
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
     * @throws ErrorException
     */
    public function handleError($level, $message, $file = '', $line = 0)
    {
        if (error_reporting() & $level) {
            throw new ErrorException($message, 0, $level, $file, $line);
        } else {
            $this->addMessage($message, 'deprecation');
        }
    }

    /**
     * Starts a measure
     *
     * @param string $name Internal name, used to stop the measure
     * @param string $label Public name
     * @throws DebugBarException
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
     * @throws DebugBarException
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
     * Returns a JavascriptRenderer for this instance
     *
     * @param string $baseUrl
     * @param string $basePathng
     * @return JavascriptRenderer
     */
    public function getJavascriptRenderer($baseUrl = null, $basePath = null)
    {
        if ($this->jsRenderer === null) {
            $this->jsRenderer = new JavascriptRenderer($this, $baseUrl, $basePath);
        }
        return $this->jsRenderer;
    }

    /**
     * Modify the response and inject the debugbar (or data in headers)
     *
     * @param ServerRequestInterface|Request $request
     * @param ResponseInterface|Response $response
     * @return ResponseInterface|Response
     * @throws DebugBarException
     * @throws \ReflectionException
     */
    public function modifyResponse(ServerRequestInterface $request, ResponseInterface $response): Response
    {
        $renderer = $this->getJavascriptRenderer('/vendor/maximebf/debugbar/src/DebugBar/Resources/');

        //Asset response
        $path = $request->getUri()->getPath();
        $baseUrl = $renderer->getBaseUrl();

        if (strpos($path, $baseUrl) === 0) {
            $file = $renderer->getBasePath() . substr($path, strlen($baseUrl));

            if (file_exists($file)) {
                $response->getBody()->write((string)file_get_contents($file));
                $extension = pathinfo($file, PATHINFO_EXTENSION);

                if (isset(self::$mimes[$extension])) {
                    return $response->withHeader('Content-Type', self::$mimes[$extension]);
                }

                return $response; //@codeCoverageIgnore
            }
        }

        $isAjax = strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';

        if ($this->shouldCollect('config', false)) {
            try {
                $configCollector = new ConfigCollector();
                $configCollector->setData($this->kernel->getConfig()->toArray());
                $this->addCollector($configCollector);
            } catch (\Exception $e) {
                $this->addThrowable(
                    new Exception(
                        'Cannot add ConfigCollector to Laravel Debugbar: ' . $e->getMessage(),
                        $e->getCode(),
                        $e
                    )
                );
            }
        }

        if ($request->hasSession()){

            /** @var SessionInterface $session */
            $session = $request->getSession();

            if ($this->shouldCollect('session') && ! $this->hasCollector('session')) {
                try {
                    $this->addCollector(new SessionCollector($session));
                } catch (\Exception $e) {
                    $this->addThrowable(
                        new Exception(
                            'Cannot add SessionCollector to Laravel Debugbar: ' . $e->getMessage(),
                            $e->getCode(),
                            $e
                        )
                    );
                }
            }
        } else {
            $session = null;
        }

        if ($this->shouldCollect('request', true)) {
            $this->addCollector(new RequestCollector($request, $response));
        }

        if ($this->shouldCollect('db', true)) {
            if ($this->container->has('database.connection')) {
                $mysqliCollector = new QueryCollector(null, $this->getCollector('time'));
                $connectionNames = array_keys(getConfig('database.connections'));
                foreach ($connectionNames as $connectionName) {
                    $mysqliCollector->addLogger(
                        $this->container
                            ->get(sprintf('database.connection.%s', $connectionName))
                            ->getQueryLogger(),
                        (string)$connectionName
                    );
                    $mysqliCollector->setDataFormatter(new QueryFormatter());
                }

                $this->addCollector($mysqliCollector);
            }
        }
        if (!$this->isEnabled() || $this->isDebugbarRequest($request)) {
            return $response;
        }

        // Show the Http Response Exception in the Debugbar, when available
        if (isset($response->exception)) {
            $this->addThrowable($response->exception);
        }

        if ($response->isRedirection()) {
            try {
                return $this->handleRedirect($response);
            } catch (Exception $e) {
                $this->logger->error('Debugbar exception: ' . $e->getMessage());
            }
        } elseif (
            $request->expectsJson() &&
            getConfig('profiler.debugbar.capture_ajax', true)
        ) {
            try {
                $headers = $this->getDataAsHeaders();

                foreach ($headers as $name => $value) {
                    $response = $response->withHeader($name, $value);
                }
                $this->sendDataInHeaders(true);

                if (getConfig('profiler.debugbar.add_ajax_timing', false)) {
                    $response = $this->addServerTimingHeaders($response);
                }
            } catch (Exception $e) {
                $this->logger->error('Debugbar exception: ' . $e->getMessage());
            }
        } elseif (
            ($response->getHeaderLine('Content-Type') &&
                strpos($response->getHeaderLine('Content-Type'), 'html') === false) ||
            $this->isJsonRequest($request)
        ) {
            try {
                // Just collect + store data, don't inject it.
                $this->collect();
            } catch (Exception $e) {
                $this->logger->error('Debugbar exception: ' . $e->getMessage());
            }
        } elseif (getConfig('profiler.debugbar.inject', true)) {
            try {
                return $this->injectDebugbar($response);
            } catch (Exception $e) {
                $this->logger->error('Debugbar exception: ' . $e->getMessage());
            }
        }

        return $response;
    }

    /**
     * Injects the web debug toolbar into the given Response.
     *
     * @param Response $response A Response instance
     */
    public function injectDebugbar(Response $response)
    {
        $content = (string)$response->getBody();

        $renderer = $this->getJavascriptRenderer();
        if ($this->getStorage()) {
            $openHandlerUrl = route('profiler.debugbar.openhandler');
            $renderer->setOpenHandlerUrl($openHandlerUrl);
        }

        $renderedContent = $renderer->renderHead() . $renderer->render();

        $pos = strripos($content, '</body>');
        if (false !== $pos) {
            $content = substr($content, 0, $pos) . $renderedContent . substr($content, $pos);
        } else {
            $content = $content . $renderedContent;
        }

        $body = (new Psr17Factory())->createStream();
        $body->write($content);

        return $response
            ->withBody($body)
            ->withoutHeader('Content-Length');
    }

    /**
     * Check if the Debugbar is enabled
     * @return boolean
     */
    public function isEnabled()
    {
        if ($this->enabled === null) {
            $configEnabled = getConfig('profiler.debugbar.enabled');

            if ($configEnabled === null) {
                $configEnabled = $this->kernel->isDebug();
            }

            $this->enabled = (bool)$configEnabled;
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
    protected function selectStorage(DebugBar $debugbar)
    {
        $config = $this->kernel->getConfig();
        if ($config->get('profiler.debugbar.storage.enabled')) {
            $driver = $config->get('profiler.debugbar.storage.driver', 'file');

            switch ($driver) {
                // TODO: More storage
                case 'file':
                default:
                    $path = $config->get(
                        'profiler.debugbar.storage.path',
                        $this->kernel->getCacheDir() . "profiler" . DIRECTORY_SEPARATOR . "debugbar"
                    );
                    $storage = $this->container->make(FilesystemStorage::class, [
                        'arguments' => ['dirname' => $path],
                    ]);
                    break;
            }

            $debugbar->setStorage($storage);
        }
    }

    /**
     * Add Server-Timing headers for the TimeData collector
     *
     * @see https://www.w3.org/TR/server-timing/
     * @param Response $response
     * @return Response
     * @throws DebugBarException
     */
    protected function addServerTimingHeaders(Response $response): Response
    {
        if ($this->hasCollector('time')) {
            $collector = $this->getCollector('time');

            $headers = [];
            foreach ($collector->collect()['measures'] as $k => $m) {
                $headers[] = sprintf('app;desc="%s";dur=%F', str_replace('"', "'", $m['label']), $m['duration'] * 1000);
            }

            foreach ($headers as $header) {
                $response = $response->withHeader('Server-Timing', $header);
            }
        }
        return $response;
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected function isJsonRequest(Request $request)
    {
        // If XmlHttpRequest or Live, return true
        if ('XMLHttpRequest' == $request->getHeaderLine('X-Requested-With')) {
            return true;
        }

        // Check if the request wants Json
        $acceptable = $request->expectsJson();
        return (isset($acceptable[0]) && $acceptable[0] == 'application/json');
    }

    /**
     * Check if this is a request to the Debugbar OpenHandler
     *
     * @return bool
     */
    protected function isDebugbarRequest(Request $request)
    {
        return $request->is(getConfig('profiler.route_prefix', '_profiler/*'));
    }

    /**
     * Handle redirection responses
     */
    private function handleRedirect(ResponseInterface $response): ResponseInterface
    {
        if ($this->isDataPersisted() || session_status() === PHP_SESSION_ACTIVE) {
            $this->stackData();
        }

        return $response;
    }
}

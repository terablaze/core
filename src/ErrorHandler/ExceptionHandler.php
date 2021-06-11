<?php

namespace TeraBlaze\ErrorHandler;

use Exception;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TeraBlaze\ErrorHandler\Exception\Http\HttpException;
use TeraBlaze\ErrorHandler\Exception\Http\HttpExceptionInterface;
use TeraBlaze\HttpBase\JsonResponse;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;
use Throwable;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;

class ExceptionHandler
{
    /**
     * The container implementation.
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    protected bool $debugMode;

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [];

    /**
     * The callbacks that should be used during reporting.
     *
     * @var array
     */
    protected $reportCallbacks = [];

    /**
     * The callbacks that should be used during rendering.
     *
     * @var array
     */
    protected $renderCallbacks = [];

    /**
     * The registered exception mappings.
     *
     * @var array
     */
    protected $exceptionMap = [];

    /**
     * A list of the internal exception types that should not be reported.
     *
     * @var string[]
     */
    protected $internalDontReport = [];

    /**
     * Create a new exception handler instance.
     *
     * @param  ContainerInterface  $container
     * @return void
     */
    public function __construct(ContainerInterface $container, bool $debugMode = true)
    {
        $this->container = $container;
        $this->debugMode = $debugMode;

        $this->register();
    }

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Indicate that the given exception type should not be reported.
     *
     * @param  string  $class
     * @return $this
     */
    protected function ignore(string $class)
    {
        $this->dontReport[] = $class;

        return $this;
    }

    /**
     * Report or log an exception.
     *
     * @param  Throwable  $e
     * @return void
     *
     * @throws Throwable
     */
    public function report(Throwable $e, ?ServerRequestInterface $request = null)
    {
        if ($request == null) {
            $request = Request::createFromGlobals();
        }
        $e = $this->mapException($e);

        if ($this->shouldntReport($e)) {
            return;
        }

        try {
            /** @var LoggerInterface $logger */
            $logger = $this->container->has('logger.exception')
                ? $this->container->get('logger.exception')
                : ($this->container->has(LoggerInterface::class)
                    ? $this->container->get(LoggerInterface::class)
                    : null);
        } catch (Exception $ex) {
            throw $e;
        }

        if (!is_null($logger)) {
            $logger->error(
                $e->getMessage(),
                array_merge(
                    $this->exceptionContext($e),
                    $this->context($request),
                    ['exception' => $e->getTraceAsString()]
                )
            );
        }
    }

    /**
     * Determine if the exception should be reported.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    public function shouldReport(Throwable $e): bool
    {
        return !$this->shouldntReport($e);
    }

    /**
     * Determine if the exception is in the "do not report" list.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function shouldntReport(Throwable $e): bool
    {
        $dontReport = array_merge($this->dontReport, $this->internalDontReport);

        foreach ($dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the default exception context variables for logging.
     *
     * @param  \Throwable  $e
     * @return array
     */
    protected function exceptionContext(Throwable $e): array
    {
        if (method_exists($e, 'context')) {
            return $e->context();
        }

        return [];
    }

    /**
     * Get the default context variables for logging.
     *
     * @return array
     */
    protected function context(ServerRequestInterface $request): array
    {
        return [
            'path' => $request->getUri()->__toString(),
            'user_agent' => $request->getServerParams()['HTTP_USER_AGENT'],
            'ip_address' => $request->getServerParams()['REMOTE_ADDR'],
        ];
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param Throwable $e
     * @param ServerRequestInterface|Request|null $request
     * @return Response
     */
    public function render(Throwable $e, ?ServerRequestInterface $request = null): Response
    {
        if ($request == null) {
            $request = Request::createFromGlobals();
        }
        $e = $this->prepareException($this->mapException($e));
        return $request->expectsJson()
            ? $this->prepareJsonResponse($request, $e)
            : $this->prepareResponse($request, $e);
    }

    /**
     * Map the exception using a registered mapper if possible.
     *
     * @param  \Throwable  $e
     * @return \Throwable
     */
    protected function mapException(Throwable $e)
    {
        foreach ($this->exceptionMap as $class => $mapper) {
            if (is_a($e, $class)) {
                return $mapper($e);
            }
        }

        return $e;
    }

    /**
     * Prepare exception for rendering.
     *
     * @param  \Throwable  $e
     * @return \Throwable
     */
    protected function prepareException(Throwable $e)
    {
        // if ($e instanceof ModelNotFoundException) {
        //     $e = new NotFoundHttpException($e->getMessage(), $e);
        // } elseif ($e instanceof AuthorizationException) {
        //     $e = new AccessDeniedHttpException($e->getMessage(), $e);
        // } elseif ($e instanceof TokenMismatchException) {
        //     $e = new HttpException(419, $e->getMessage(), $e);
        // } elseif ($e instanceof SuspiciousOperationException) {
        //     $e = new NotFoundHttpException('Bad hostname provided.', $e);
        // } elseif ($e instanceof RecordsNotFoundException) {
        //     $e = new NotFoundHttpException('Not found.', $e);
        // }

        return $e;
    }

    /**
     * Prepare a response for the given exception.
     *
     * @param  Request  $request
     * @param  \Throwable  $e
     * @return Response
     */
    protected function prepareResponse($request, Throwable $e)
    {
        if (!$this->isHttpException($e) && $this->debugMode) {
            return $this->convertExceptionToResponse($e);
        }

        if (!$this->isHttpException($e)) {
            $e = new HttpException(500, $e->getMessage());
        }

        return $this->renderHttpException($e);
    }

    /**
     * Create a response for the given exception.
     *
     * @param  \Throwable  $e
     * @return Response
     */
    protected function convertExceptionToResponse(Throwable $e)
    {
        return new Response(
            $this->renderExceptionContent($e),
            $this->isHttpException($e) ? $e->getStatusCode() : 500,
            $this->isHttpException($e) ? $e->getHeaders() : ['Content-Type' => 'text/html']
        );
    }

    /**
     * Get the response content for the given exception.
     *
     * @param  \Throwable  $e
     * @return string
     */
    protected function renderExceptionContent(Throwable $e)
    {
        try {
            return $this->debugMode && class_exists(Whoops::class)
                ? $this->renderExceptionWithWhoops($e)
                : $this->renderExceptionWithTeraBlaze($e, $this->debugMode);
        } catch (Exception $e) {
            return $this->renderExceptionWithTeraBlaze($e, $this->debugMode);
        }
    }

    /**
     * Render an exception to a string using "Whoops".
     *
     * @param  \Throwable  $e
     * @return string
     */
    protected function renderExceptionWithWhoops(Throwable $e)
    {
        $whoops = new Whoops();
        $whoops->appendHandler(new PrettyPageHandler());
        $whoops->writeToOutput(false);
        $whoops->allowQuit(false);
        return $whoops->handleException($e);
    }

    /**
     * Render an exception to a string using TeraBlaze.
     *
     * @param  \Throwable  $e
     * @param  bool  $debug
     * @return string
     */
    protected function renderExceptionWithTeraBlaze(Throwable $e, $debug)
    {
        $renderer = new HtmlErrorRenderer($debug);

        return $renderer->render($e)->getAsString();
    }

    /**
     * Render the given HttpException.
     *
     * @param  HttpExceptionInterface  $e
     * @return Response
     */
    protected function renderHttpException(HttpExceptionInterface $e)
    {
        return $this->convertExceptionToResponse($e);
    }

    /**
     * Prepare a JSON response for the given exception.
     *
     * @param  Request  $request
     * @param  \Throwable  $e
     * @return JsonResponse
     */
    protected function prepareJsonResponse($request, Throwable $e)
    {
        return (new JsonResponse(
            $this->convertExceptionToArray($e),
            $this->isHttpException($e) ? $e->getStatusCode() : 500,
            $this->isHttpException($e) ? $e->getHeaders() : ['Content-Type' => 'application/json']
        ))->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Convert the given exception to an array.
     *
     * @param  \Throwable  $e
     * @return array
     */
    protected function convertExceptionToArray(Throwable $e)
    {
        return $this->debugMode ? [
            'status_code' => $this->isHttpException($e) ? $e->getStatusCode() : 500,
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTrace(),
        ] : [
            'message' => $this->isHttpException($e) ? $e->getMessage() : 'Server Error',
        ];
    }

    /**
     * Determine if the given exception is an HTTP exception.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function isHttpException(Throwable $e): bool
    {
        return $e instanceof HttpExceptionInterface;
    }

    /**
     * Triggers a silenced deprecation notice.
     *
     * @param string $package The name of the Composer package that is triggering the deprecation
     * @param string $version The version of the package that introduced the deprecation
     * @param string $message The message of the deprecation
     * @param mixed  ...$args Values to insert in the message using printf() formatting
     */
    public static function triggerDeprecation(
        string $package,
        string $version,
        string $message,
        ...$args
    ): void {
        @trigger_error(($package || $version ? "Since $package $version: " : '') .
            ($args ? vsprintf($message, $args) : $message), \E_USER_DEPRECATED);
    }
}

<?php

namespace TeraBlaze\Core\Kernel;

use Psr\Http\Message\ResponseInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Kernel\Event\ExceptionEvent;
use TeraBlaze\Core\Kernel\Event\FinishRequestEvent;
use TeraBlaze\Core\Kernel\Event\RequestEvent;
use TeraBlaze\Core\Kernel\Event\ResponseEvent;
use TeraBlaze\Core\Kernel\Event\TerminateEvent;
use TeraBlaze\ErrorHandler\Exception\Http\BadRequestHttpException;
use TeraBlaze\ErrorHandler\Exception\Http\HttpExceptionInterface;
use TeraBlaze\EventDispatcher\Dispatcher;
use TeraBlaze\HttpBase\Exception\RequestExceptionInterface;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\Profiler\Debugbar\DebugbarMiddleware;

class HttpKernel implements HttpKernelInterface, TerminableInterface
{
    protected Dispatcher $dispatcher;
    private Container $container;
    private array $middlewares;

    public function __construct(
        Container $container,
        Dispatcher $dispatcher,
        array $middlewares
    ) {
        $this->container = $container;
        $this->dispatcher = $dispatcher;
        $this->middlewares = $middlewares;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, bool $catch = true): ResponseInterface
    {
        /** @var Request $request */
        $request = $request->withAddedHeader('X-Php-Ob-Level', (string) ob_get_level());

        try {
            return $this->handleRaw($request);
        } catch (\Exception $e) {
            if ($e instanceof RequestExceptionInterface) {
                $e = new BadRequestHttpException($e->getMessage(), $e);
            }
            if (false === $catch) {
                $this->finishRequest($request);

                throw $e;
            }

            return $this->handleThrowable($e, $request);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->dispatcher->dispatch(new TerminateEvent($this, $request, $response));
    }

    /**
     * @throws \Exception|\Throwable
     * @internal
     */
    public function terminateWithException(\Throwable $exception, Request $request = null): void
    {
        if (!$request) {
            throw $exception;
        }

        $response = $this->handleThrowable($exception, $request);

        $response->send();

        $this->terminate($request, $response);
    }

    /**
     * Handles a request to convert it to a response.
     *
     * Exceptions are not caught.
     *
     * @param Request $request
     * @return ResponseInterface
     */
    private function handleRaw(Request $request): ResponseInterface
    {
        $event = new RequestEvent($this, $request);
        $this->dispatcher->dispatch($event);

        if ($event->hasResponse()) {
            return $this->filterResponse($event->getResponse(), $request);
        }

        $handler = new Handler($this->middlewares);

        $this->container->registerServiceInstance('request', $request);

        /** @var Response $response */
        $response = $handler->handle($request);

        return $this->filterResponse($response, $request);
    }

    /**
     * Filters a response object.
     *
     * @throws \RuntimeException if the passed object is not a Response instance
     */
    private function filterResponse(Response $response, Request $request): Response
    {
        $event = new ResponseEvent($this, $request, $response);

        $this->dispatcher->dispatch($event);

        $this->finishRequest($request);

        return $event->getResponse();
    }

    /**
     * Publishes the finish request event, then pop the request from the stack.
     *
     * Note that the order of the operations is important here, otherwise
     * operations such as {@link RequestStack::getParentRequest()} can lead to
     * weird results.
     */
    private function finishRequest(Request $request): void
    {
        $this->dispatcher->dispatch(new FinishRequestEvent($this, $request));
    }

    /**
     * Handles a throwable by trying to convert it to a Response.
     *
     * @throws \Exception
     */
    private function handleThrowable(\Throwable $e, Request $request): Response
    {
        $event = new ExceptionEvent($this, $request, $e);
        $this->dispatcher->dispatch($event);

        // a listener might have replaced the exception
        $e = $event->getThrowable();

        if (!$event->hasResponse()) {
            $this->finishRequest($request);

            throw $e;
        }

        $response = $event->getResponse();

        // the developer asked for a specific status code
        if (
            !$event->isAllowingCustomResponseCode() && !$response->isClientError()
            && !$response->isServerError() && !$response->isRedirect()
        ) {
            // ensure that we actually have an error response
            if ($e instanceof HttpExceptionInterface) {
                // keep the HTTP status code and headers
                $response = $response->withStatus($e->getStatusCode());
                foreach ($e->getHeaders() as $header => $value) {
                    $response = $response->withAddedHeader($header, $value);
                }
            } else {
                $response->withStatus(500);
            }
        }

        try {
            return $this->filterResponse($response, $request);
        } catch (\Exception $e) {
            return $response;
        }
    }

    /**
     * Returns a human-readable string for the specified variable.
     * @param mixed $var
     * @return string
     */
    private function varToString($var): string
    {
        if (\is_object($var)) {
            return sprintf('an object of type %s', \get_class($var));
        }

        if (\is_array($var)) {
            $a = [];
            foreach ($var as $k => $v) {
                $a[] = sprintf('%s => ...', $k);
            }

            return sprintf('an array ([%s])', mb_substr(implode(', ', $a), 0, 255));
        }

        if (\is_resource($var)) {
            return sprintf('a resource (%s)', get_resource_type($var));
        }

        if (null === $var) {
            return 'null';
        }

        if (false === $var) {
            return 'a boolean value (false)';
        }

        if (true === $var) {
            return 'a boolean value (true)';
        }

        if (\is_string($var)) {
            return sprintf('a string ("%s%s")', mb_substr($var, 0, 255), mb_strlen($var) > 255 ? '...' : '');
        }

        if (is_numeric($var)) {
            return sprintf('a number (%s)', (string) $var);
        }

        return (string) $var;
    }
}

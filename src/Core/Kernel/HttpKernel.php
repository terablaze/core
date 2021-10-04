<?php

namespace TeraBlaze\Core\Kernel;

use Psr\Http\Message\ResponseInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Core\Kernel\Events\ExceptionEvent;
use TeraBlaze\Core\Kernel\Events\FinishRequestEvent;
use TeraBlaze\Core\Kernel\Events\RequestEvent;
use TeraBlaze\Core\Kernel\Events\ResponseEvent;
use TeraBlaze\Core\Kernel\Events\TerminateEvent;
use TeraBlaze\ErrorHandler\Exception\Http\BadRequestHttpException;
use TeraBlaze\ErrorHandler\Exception\Http\HttpExceptionInterface;
use TeraBlaze\EventDispatcher\Dispatcher;
use TeraBlaze\HttpBase\Exception\RequestExceptionInterface;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;

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

        $handler = new Handler($this->container, $this->middlewares);

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

        $this->finishRequest($event->getRequest());

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
}

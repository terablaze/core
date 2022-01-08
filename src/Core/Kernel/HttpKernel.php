<?php

namespace TeraBlaze\Core\Kernel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Controller\ControllerInterface;
use TeraBlaze\Core\Kernel\Events\ExceptionEvent;
use TeraBlaze\Core\Kernel\Events\FinishRequestEvent;
use TeraBlaze\Core\Kernel\Events\RequestEvent;
use TeraBlaze\Core\Kernel\Events\ResponseEvent;
use TeraBlaze\Core\Kernel\Events\TerminateEvent;
use TeraBlaze\ErrorHandler\Exception\Http\BadRequestHttpException;
use TeraBlaze\ErrorHandler\Exception\Http\HttpExceptionInterface;
use TeraBlaze\ErrorHandler\Exception\Http\NotFoundHttpException;
use TeraBlaze\EventDispatcher\Dispatcher;
use TeraBlaze\HttpBase\Exception\RequestExceptionInterface;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\Routing\Events\PostControllerEvent;
use TeraBlaze\Routing\Events\PreControllerEvent;
use TeraBlaze\Routing\Exception\ImplementationException;
use TeraBlaze\Routing\Route;

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

        array_push($this->middlewares, [$this->container->get(HttpKernel::class), 'pass']);

        /** @var Handler $handler */
        $handler = $this->container->make(Handler::class, [
            "alias" => RequestHandlerInterface::class,
            "arguments" => [
                "queue" => $this->middlewares,
            ]
        ]);

        /** @var Response $response */
        $response = $handler->handle($request);

        return $this->filterResponse($response, $request);
    }

    /**
     * @param ServerRequestInterface $request
     */
    public function pass(
        ServerRequestInterface $request
    ): ResponseInterface {
        /** @var Route $route */
        $route = $request->getAttribute('route');
        if ($route->isCallableRoute()) {
            $response = $this->container->call($route->getCallable(), $route->getParameters());
            if (!$response instanceof ResponseInterface) {
                throw new ImplementationException(
                    sprintf(
                        "Result of this closure is of type %s, ensure the an instance of %s is returned",
                        gettype($response),
                        ResponseInterface::class
                    )
                );
            }
            return $response;
        }

        $controller = $route->getController();
        $action = $route->getAction();
        $parameters = $route->getParameters();

//        $event = new PreControllerEvent(router(), $request, $controller);
//        $controller = $event->getController();

        $className = ucfirst($controller);

        if (!class_exists($className)) {
            throw new NotFoundHttpException("Controller '{$className}' not found");
        }

        if (!$this->container->has($className)) {
            $this->container->registerService($className, ['class' => $className]);
        }

        $controllerInstance = $this->container->get($className);
        if ($controllerInstance instanceof ControllerInterface) {
            $controllerInstance->setContainer($this->container);
        }

        $event = new PostControllerEvent(router(), $request, $controllerInstance);
        $controllerInstance = $event->getControllerInstance();

        if (!method_exists($controllerInstance, $action)) {
            throw new NotFoundHttpException("Action '{$action}' not found");
        }

        $response = $this->container->call([$controllerInstance, $action], $parameters);

        if (is_null($response)) {
            throw new ImplementationException(
                "Result of {$className}::{$action}() is either empty or null, " .
                "ensure the controller's action {$className}::{$action}() " .
                "is properly implemented and returns an instance of " . ResponseInterface::class
            );
        }

        if (!$response instanceof ResponseInterface) {
            throw new ImplementationException(
                "Result of {$className}::{$action}() is of type " . gettype($response) .
                ", ensure the controller's action {$className}::{$action}() " .
                "returns an instance of " . ResponseInterface::class
            );
        }

        return $response;
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

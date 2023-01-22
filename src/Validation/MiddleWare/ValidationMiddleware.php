<?php

namespace Terablaze\Validation\MiddleWare;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Terablaze\Container\Container;
use Terablaze\HttpBase\RedirectResponse;
use Terablaze\HttpBase\Request;
use Terablaze\HttpBase\Response;
use Terablaze\HttpBase\Traits\ResponseTrait;
use Terablaze\Routing\Generator\UrlGenerator;
use Terablaze\Routing\Generator\UrlGeneratorInterface;
use Terablaze\Routing\Router;
use Terablaze\Routing\RouterInterface;
use Terablaze\Validation\Exception\AuthorizationException;
use Terablaze\Validation\Exception\ValidationException;
use Terablaze\Validation\Validation;
use Terablaze\Validation\Validator;

abstract class ValidationMiddleware implements MiddlewareInterface
{
    use ResponseTrait;

    protected Container $container;

    protected Router $router;

    protected Validator $validator;

    protected ?Validation $validation = null;

    protected string $submitName = "submit";

    /**
     * The URI to redirect to if validation fails.
     *
     * @var string
     */
    protected ?string $redirect = null;

    /**
     * The route name to redirect to if validation fails.
     *
     * @var string
     */
    protected ?string $redirectRoute = null;

    /**
     * Indicates whether validation should stop after the first rule failure.
     *
     * @var bool
     */
    protected $stopOnFirstFailure = false;

    public function __construct(Container $container, RouterInterface $router, Validator $validator )
    {
        $this->container = $container;
        $this->router = $router;
        $this->validator = $validator;
    }

    /**
     * Get the URL to redirect to on a validation error.
     *
     * @return string
     * @throws \ReflectionException
     * @throws \Terablaze\Collection\Exceptions\TypeException
     * @throws \Terablaze\Routing\Exception\MissingParametersException
     * @throws \Terablaze\Routing\Exception\RouteNotFoundException
     */
    protected function getRedirectUrl()
    {
        /** @var UrlGenerator $urlGenerator */
        $urlGenerator = $this->router->getGenerator();

        if ($this->redirect) {
            return $this->redirect;
        }

        if (is_string($this->redirectRoute)) {
            return $urlGenerator->generate($this->redirectRoute);
        }
        if (is_array($this->redirectRoute)) {
            return $urlGenerator->generate(
                $this->redirectRoute['name'],
                $this->redirectRoute['parameters'] ?? [],
                $this->redirectRoute['reference_type'] ?? UrlGeneratorInterface::ABSOLUTE_URL,
                $this->redirectRoute['locale'] ?? null,
            );
        }
        return $urlGenerator->previous();
    }

    /**
     * Get the validated data from the request.
     *
     * @return array
     */
    public function validated()
    {
        return $this->validation->validated();
    }

    /**
     * Get rules for validation.
     *
     * @return array
     */
    public abstract function rules();

    /**
     * Get custom messages for validation errors.
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }

    /**
     * Get custom fields for validator errors.
     *
     * @return array
     */
    public function fields()
    {
        return [];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validation  $validation
     * @return RedirectResponse|ResponseInterface
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validation $validation, Request $request)
    {
        if ($request->hasFlash()) {
            $request->getSession()->flashInput($request->all());
            $request->getFlash()->flash('_validation_errors', $validation->errors()->messages());
        }
        return $this->redirect($this->getRedirectUrl());
    }

    /**
     * Determine if the request passes the authorization check.
     *
     * @return bool
     */
    protected function passesAuthorization()
    {
        if (method_exists($this, 'authorize')) {
            return $this->authorize();
        }
        return true;
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return RedirectResponse|ResponseInterface
     *
     * @throws AuthorizationException
     */
    protected function failedAuthorization(Request $request)
    {
        $authException = new AuthorizationException();
        if (Validation::$throwException) {
            throw $authException;
        }
        if ($request->hasFlash()) {
            $request->getFlash()->flash('_authorization_errors', $authException->getMessage());
        }
        return $this->redirect($this->getRedirectUrl());
    }

    /**
     * @param ServerRequestInterface|Request $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->passesAuthorization()) {
            $response = $this->failedAuthorization($request);
        }

        if (isset($request->all()[$this->submitName])) {
            $validation = $request->validate($this->rules(), $this->messages(), $this->fields());
            if ($validation->fails()) {
                $response = $this->failedValidation($validation, $request);
            }
        }
        if (isset($response) && $response instanceof ResponseInterface) {
            return $response;
        }
        return $handler->handle($request);
    }
}
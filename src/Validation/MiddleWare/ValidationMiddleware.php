<?php

namespace TeraBlaze\Validation\MiddleWare;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\HttpBase\Request;
use TeraBlaze\HttpBase\Traits\ResponseTrait;
use TeraBlaze\Routing\Generator\UrlGenerator;
use TeraBlaze\Routing\Generator\UrlGeneratorInterface;
use TeraBlaze\Routing\Router;
use TeraBlaze\Routing\RouterInterface;
use TeraBlaze\Validation\Exception\AuthorizationException;
use TeraBlaze\Validation\Exception\ValidationException;
use TeraBlaze\Validation\Validation;
use TeraBlaze\Validation\Validator;

abstract class ValidationMiddleware implements MiddlewareInterface
{
    use ResponseTrait;

    protected Container $container;

    protected Router $router;

    protected Validator $validator;

    protected ?Validation $validation = null;

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
     * @throws \TeraBlaze\Collection\Exceptions\TypeException
     * @throws \TeraBlaze\Routing\Exception\MissingParametersException
     * @throws \TeraBlaze\Routing\Exception\RouteNotFoundException
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
     * @return void
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validation $validation, Request $request)
    {
        if ($request->hasFlash()) {
            $request->getSession()->flashInput($request->all());
            $request->getFlash()->flash('_validation_errors', $validation->errors());
        }
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
     * @return void
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
    }

    /**
     * @param ServerRequestInterface|Request $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->passesAuthorization()) {
            $this->failedAuthorization($request);
        }

        if (!empty($request->all())) {
            $validation = $request->validate($this->rules(), $this->messages(), $this->fields());
            if ($validation->fails()) {
                $this->failedValidation($validation, $request);
            }
        }
        return $handler->handle($request);
    }
}
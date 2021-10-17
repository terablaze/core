<?php

namespace TeraBlaze\Controller;

use ReflectionException;
use TeraBlaze\Container\Exception\ContainerException;
use TeraBlaze\Container\Exception\ParameterNotFoundException;
use TeraBlaze\HttpBase\JsonResponse;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\Container\ContainerAwareTrait;
use TeraBlaze\Routing\Generator\UrlGeneratorInterface;
use TeraBlaze\Routing\RouterInterface;
use TeraBlaze\Session\Traits\SessionAwareTrait;
use TeraBlaze\View\Template;
use TeraBlaze\View\View;

/**
 * Class AbstractController
 * @package TeraBlaze\AbstractController
 */
abstract class AbstractController implements ControllerInterface
{
    use ContainerAwareTrait;
    use SessionAwareTrait;

    protected $name;

    public $global;

    public function getName(): string
    {
        if (empty($this->name)) {
            $this->name = get_class($this);
        }
        return $this->name;
    }
    /**
     * Returns a rendered view.
     *
     * @param string $viewFile
     * @param array $parameters
     * @param bool $asString
     * @return string|Template
     * @throws ReflectionException
     */
    protected function renderView(string $viewFile, array $parameters = [], bool $asString = true)
    {
        if (!$this->container->has(View::class)) {
            throw new \LogicException(
                'You can not use the "renderView" method if the ViewParcel is not in use.
                Try loading the ViewParcel in the parcels configuration file.'
            );
        }

        /** @var View $view */
        $view = $this->container->get(View::class);

        $template = $view->render($viewFile, $parameters);

        if ($asString) {
            return $template->render();
        }

        return $template;
    }

    /**
     * Renders a view.
     */
    protected function render(
        string $view,
        array $parameters = [],
        int $responseCode = 200,
        array $headers = ['Content-Type' => 'text/html']
    ): Response {
        $content = $this->renderView($view, $parameters);
        return new Response($content, $responseCode, $headers);
    }

    protected function json(
        $data,
        int $responseCode = 200,
        array $headers = ['Content-Type' => 'application/json'],
        $jsonOptions = null
    ): JsonResponse {
        $response = new JsonResponse($data, $responseCode, $headers);
        if ($jsonOptions) {
            $response = $response->setEncodingOptions($jsonOptions);
        }
        return $response;
    }

    /**
     * Generates a URL from the given parameters.
     *
     * @param string $routeName
     * @param array $parameters
     * @param int $referenceType
     * @return string
     * @throws ReflectionException
     * @see UrlGeneratorInterface
     */
    protected function generateUrl(
        string $routeName,
        array $parameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): string {
        return $this->container->get(RouterInterface::class)->generate($routeName, $parameters, $referenceType);
    }

    /**
     * @param string $url
     * @param int $status
     * @return Response
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        return new Response('', $status, [
            'Location' => $url
        ]);
    }

    /**
     * @param string $routeName
     * @param array $parameters
     * @param int $status
     * @param int $referenceType
     * @return Response
     * @throws ReflectionException
     * @throws ContainerException
     * @throws ParameterNotFoundException
     */
    protected function redirectToRoute(
        string $routeName,
        array $parameters = [],
        int $status = 302,
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): Response {
        return $this->redirect($this->generateUrl($routeName, $parameters, $referenceType), $status);
    }
}

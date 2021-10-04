<?php

namespace TeraBlaze\Controller;

use ReflectionException;
use TeraBlaze\Container\Exception\ContainerException;
use TeraBlaze\Container\Exception\ParameterNotFoundException;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\Container\ContainerAwareTrait;
use TeraBlaze\Events\Events;
use TeraBlaze\Routing\Generator\UrlGeneratorInterface;
use TeraBlaze\Routing\RouterInterface;
use TeraBlaze\Session\Traits\SessionAwareTrait;
use TeraBlaze\View\Exception\Argument as ViewArgumentException;
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
     * @param string $view
     * @param array $parameters
     * @param bool $asString
     * @return mixed
     * @throws ReflectionException
     */
    protected function renderView(string $view, array $parameters = [], bool $asString = true)
    {
        if (!$this->container->has(View::class)) {
            throw new \LogicException(
                'You can not use the "renderView" method if the ViewParcel is not in use.
                Try loading the ViewParcel in the parcels configuration file.'
            );
        }

        $view = $this->container->get(View::class)->render($view, $parameters);

        if ($asString) {
            return $view->render();
        }

        return $view;
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
    ): Response {
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data, $jsonOptions);
        }
        return new Response($data, $responseCode, $headers);
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

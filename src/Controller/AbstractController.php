<?php

namespace Terablaze\Controller;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Terablaze\Container\Exception\ContainerException;
use Terablaze\Container\Exception\ParameterNotFoundException;
use Terablaze\HttpBase\JsonResponse;
use Terablaze\HttpBase\Response;
use Terablaze\Container\ContainerAwareTrait;
use Terablaze\HttpBase\Traits\ResponseTrait;
use Terablaze\Routing\Generator\UrlGeneratorInterface;
use Terablaze\Routing\RouterInterface;
use Terablaze\Session\Traits\SessionAwareTrait;
use Terablaze\View\Template;
use Terablaze\View\View;

/**
 * Class AbstractController
 * @package Terablaze\AbstractController
 */
abstract class AbstractController implements ControllerInterface
{
    use ContainerAwareTrait;
    use SessionAwareTrait;
    use ResponseTrait;

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
     *
     * @param string $view
     * @param array<string, mixed> $parameters
     * @param int $responseCode
     * @param array<string, string> $headers
     *
     * @return Response
     * @throws ReflectionException
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
}

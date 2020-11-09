<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/4/2017
 * Time: 4:09 PM
 */

namespace TeraBlaze\Controller;

use ReflectionException;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\Container\ContainerAwareTrait;
use TeraBlaze\Controller\Exception as Exception;
use TeraBlaze\Events\Events;
use TeraBlaze\Router\Generator\UrlGeneratorInterface;

/**
 * Class Controller
 * @package TeraBlaze\Controller
 */
abstract class Controller implements ControllerInterface
{
    use ContainerAwareTrait;

    protected $name;

    public function getName(): string
    {
        if (empty($this->name)) {
            $this->name = get_class($this);
        }
        return $this->name;
    }

    /**
     * Controller destructor
     */
    public function __destruct()
    {
        Events::fire("terablaze.controller.destruct.before", array($this->getName()));

        //$this->render();

        Events::fire("terablaze.controller.destruct.after", array($this->getName()));
    }

    protected function loadView($viewFile, $viewVars = array()): string
    {
        Events::fire("terablaze.controller.view.load.before", array($viewFile, $viewVars));

        $ext = pathinfo($viewFile, PATHINFO_EXTENSION);
        $viewFile = ($ext === '') ? $viewFile . '.php' : $viewFile;
        $viewFile = str_replace("::", "/views/", $viewFile);
        $filename = $this->container->get('app.kernel')->getProjectDir() . '/src/' . $viewFile;

        if (!file_exists($filename)) {
            Events::fire("terablaze.controller.view.load.error", array($viewFile, $viewVars));
            throw new Exception\Argument("Trying to Load Non Existing View: {$viewFile}");
        }

        ob_start();
        extract($viewVars);
        $GLOBALS['viewVars'] = $viewVars;
        include $filename;
        $string = ob_get_clean();
        Events::fire("terablaze.controller.view.load.after", array($viewFile, $viewVars));

        return $string;
    }

    protected function includeView($viewFile): void
    {
        $viewVars = $GLOBALS['viewVars'];
        $ext = pathinfo($viewFile, PATHINFO_EXTENSION);
        $viewFile = ($ext === '') ? $viewFile . '.php' : $viewFile;
        $viewFile = str_replace("::", "/views/", $viewFile);
        extract($viewVars);
        $filename = $this->container->get('app.kernel')->getProjectDir() . '/src/' . $viewFile;
        include $filename;
    }

    protected function render(
        $viewFile,
        $viewVars = [],
        $responseCode = 200,
        $headers = ['Content-Type' => 'text/html']
    ): Response {
        $content = $this->loadView($viewFile, $viewVars);

        return new Response($content, $responseCode, $headers);
    }

    protected function json(
        $data,
        int $responseCode = 200,
        array $headers = ['Content-Type' => 'text/javascript'],
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
        return $this->container->get('router')->generate($routeName, $parameters, $referenceType);
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
     */
    protected function redirectToRoute(
        string $routeName,
        array $parameters = [],
        int $status = 302,
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): Response {
        $scriptName = $this->container->get('request')->getServerParams()['SCRIPT_NAME'];
        $virtualLocation = $this->container->hasParameter('virtualLocation') ?
            rtrim($this->container->getParameter('virtualLocation'), '/\\') :
            preg_replace('#public/[\w-]*.php(.*)$#', '', $scriptName);
        return $this->redirect($virtualLocation.$this->generateUrl($routeName, $parameters, $referenceType), $status);
    }
}

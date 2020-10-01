<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/4/2017
 * Time: 4:09 PM
 */

namespace TeraBlaze\Controller;

use Nyholm\Psr7\Response;
use Psr\Container\ContainerInterface;
use TeraBlaze\Container\Container;
use TeraBlaze\Controller\Exception as Exception;
use TeraBlaze\Events\Events;

use function GuzzleHttp\json_encode;

/**
 * Class Controller
 * @package TeraBlaze\Controller
 */
class Controller implements ControllerInterface
{
    protected $_name;

    /**
     * @readwrite
     */
    protected $_parameters;

    /**
     * @var Container $frameworkContainer
     */
    protected $frameworkContainer;

    public function setContainer(ContainerInterface $container): void
    {
        Events::fire("terablaze.controller.setContainer.before", array($this->getName()));

        $this->frameworkContainer = $container;

        Events::fire("terablaze.controller.setContainer.after", array($this->getName()));
    }

    public function getName(): string
    {
        if (empty($this->_name)) {
            $this->_name = get_class($this);
        }
        return $this->_name;
    }

    /**
     * @param $method
     * @return Exception\Implementation
     */
    protected function _getExceptionForImplementation($method)
    {
        return new Exception\Implementation("{$method} method not implemented");
    }

    /**
     * @return Exception\Argument
     */
    protected function _getExceptionForArgument()
    {
        return new Exception\Argument("Invalid argument");
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

    public function has(string $key): bool
    {
        return $this->frameworkContainer->has($key);
    }

    public function get(string $key): object
    {
        return $this->frameworkContainer->get($key);
    }

    public function getParameter(string $key)
    {
        return $this->frameworkContainer->getParameter($key);
    }

    public function render($viewFile, $viewVars = array(), $responseCode = 200): Response
    {
        $content = $this->loadView($viewFile, $viewVars);

        return new Response($responseCode, [], $content);
    }

    public function renderJson($data, $responseCode = 200): Response
    {
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data);
        }
        return new Response($responseCode, [], $data);
    }

    public function loadView($viewFile, $viewVars = array()): string
    {
        Events::fire("terablaze.controller.view.load.before", array($viewFile, $viewVars));
        $global = new static;
        $string = "";

        $ext = pathinfo($viewFile, PATHINFO_EXTENSION);
        $viewFile = ($ext === '') ? $viewFile . '.php' : $viewFile;
        $viewFile = str_replace("::", "/views/", $viewFile);
        $filename = $this->frameworkContainer->get('app.kernel')->getProjectDir() . '/src/' . $viewFile;

        $viewVars = array_merge($viewVars, ['global' => $global]);

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

    public function includeView($viewFile): void
    {
        $viewVars = $GLOBALS['viewVars'];
        $ext = pathinfo($viewFile, PATHINFO_EXTENSION);
        $viewFile = ($ext === '') ? $viewFile . '.php' : $viewFile;
        $viewFile = str_replace("::", "/views/", $viewFile);
        extract($viewVars);
        $filename = $this->frameworkContainer->get('app.kernel')->getProjectDir() . '/src/' . $viewFile;
        include $filename;
    }

    /**
     * serves as the default index method
     * in case it is not defined in inheriting controllers
     */
    public function index()
    {
    }
}

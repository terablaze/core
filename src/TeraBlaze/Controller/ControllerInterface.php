<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/4/2017
 * Time: 4:09 PM
 */

namespace TeraBlaze\Controller;

use Nyholm\Psr7\Response;
use TeraBlaze\Container\ContainerInterface;

/**
 * Class Controller
 * @package TeraBlaze\Controller
 */
Interface ControllerInterface 
{
	public function setContainer(ContainerInterface $container): void;

	public function getName(): string;

	public function has(string $key): bool;

	public function get(string $key): object;

	public function getParameter(string $key);

	public function render($viewFile, $viewVars = array()): Response;

	public function loadView($viewFile, $viewVars = array()): string;
}

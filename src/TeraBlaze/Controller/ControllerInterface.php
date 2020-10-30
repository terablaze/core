<?php

/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/4/2017
 * Time: 4:09 PM
 */

namespace TeraBlaze\Controller;

use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\Router\Generator\UrlGeneratorInterface;
use TeraBlaze\Router\Router;

/**
 * Class Controller
 * @package TeraBlaze\Controller
 */
Interface ControllerInterface 
{
	public function setContainer(ContainerInterface $container): void;

//	function getName(): string;
//
//	function has(string $key): bool;
//
//	function get(string $key): object;
//
//	function getParameter(string $key);
//
//	function loadView($viewFile, $viewVars = array()): string;
//
//	function includeView($viewFile): string;
//
//	function render($viewFile, $viewVars = array()): Response;
//
//  function json($data, int $status = 200, array $headers = [], array $context = []): Response;
//
//	function generateUrl(string $routeName, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH);
//
//	function redirect(string $url, int $status = 302): Response;
//
//  function redirectToRoute(string $routeName, array $parameters = [], int $status = 302): Response;
}

<?php

namespace TeraBlaze\Controller;

use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\Routing\Generator\UrlGeneratorInterface;
use TeraBlaze\Routing\Router;

/**
 * Interface ControllerInterface
 * @package TeraBlaze\Controller
 */
interface ControllerInterface
{
    public function setContainer(ContainerInterface $container);

//  function getName(): string;
//
//  function has(string $key): bool;
//
//  function get(string $key): object;
//
//  function getParameter(string $key);
//
//  function loadView($viewFile, $viewVars = array()): string;
//
//  function includeView($viewFile): string;
//
//  function render($viewFile, $viewVars = array()): Response;
//
//  function json($data, int $status = 200, array $headers = [], array $context = []): Response;
//
//  function generateUrl(string $routeName, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH);
//
//  function redirect(string $url, int $status = 302): Response;
//
//  function redirectToRoute(string $routeName, array $parameters = [], int $status = 302): Response;
}

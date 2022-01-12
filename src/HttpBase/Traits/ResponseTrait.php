<?php

namespace TeraBlaze\HttpBase\Traits;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use TeraBlaze\Container\Container;
use TeraBlaze\Container\ContainerInterface;
use TeraBlaze\Container\Exception\ContainerException;
use TeraBlaze\Container\Exception\ParameterNotFoundException;
use TeraBlaze\HttpBase\JsonResponse;
use TeraBlaze\HttpBase\RedirectResponse;
use TeraBlaze\HttpBase\Response;
use TeraBlaze\Routing\Generator\UrlGeneratorInterface;
use TeraBlaze\Routing\RouterInterface;

trait ResponseTrait
{
    /**
     * Generates a URL from the given parameters.
     *
     * @param string $routeName
     * @param array<string, mixed> $parameters
     * @param int $referenceType
     * @return string
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
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
     * @param string $routeName
     * @param array $parameters
     * @param int $status
     * @param int $referenceType
     * @return RedirectResponse
     * @throws ReflectionException
     * @throws ContainerException
     * @throws ParameterNotFoundException
     */
    public function redirectToRoute(
        string $routeName,
        array  $parameters = [],
        int    $status = 302,
        int    $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): RedirectResponse {
        return $this->redirect($this->generateUrl($routeName, $parameters, $referenceType), $status);
    }

    /**
     * @param mixed $data
     * @param int $responseCode
     * @param array<string, string> $headers
     * @param int|null $jsonOptions
     * @return JsonResponse
     */
    public function json(
        $data,
        int $responseCode = 200,
        array $headers = ['Content-Type' => 'application/json'],
        ?int $jsonOptions = null
    ): JsonResponse {
        $response = new JsonResponse($data, $responseCode, $headers);
        if ($jsonOptions) {
            $response = $response->setEncodingOptions($jsonOptions);
        }
        return $response;
    }

    /**
     * @param string $url
     * @param int $status
     * @param array $headers
     * @return RedirectResponse
     */
    public function redirect(string $url, int $status = 302, array $headers = []): RedirectResponse
    {
        return new RedirectResponse($url, $status, $headers);
    }
}
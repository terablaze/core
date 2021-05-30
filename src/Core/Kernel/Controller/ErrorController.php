<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TeraBlaze\Core\Kernel\Controller;

use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;
use TeraBlaze\HttpBase\Request;
use Symfony\Component\HttpFoundation\Response;
use TeraBlaze\Core\Kernel\Exception\HttpException;
use TeraBlaze\Core\Kernel\HttpKernelInterface;

/**
 * Renders error or exception pages from a given FlattenException.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class ErrorController
{
    private $kernel;
    private $controller;
    private $errorRenderer;

    public function __construct(HttpKernelInterface $kernel, $controller, ErrorRendererInterface $errorRenderer)
    {
        $this->kernel = $kernel;
        $this->controller = $controller;
        $this->errorRenderer = $errorRenderer;
    }

    public function __invoke(\Throwable $exception): Response
    {
        $exception = $this->errorRenderer->render($exception);

        return new Response($exception->getAsString(), $exception->getStatusCode(), $exception->getHeaders());
    }

    public function preview(Request $request, int $code): Response
    {
        /*
         * This Request mimics the parameters set by
         * \TeraBlaze\Core\Kernel\EventListener\ErrorListener::duplicateRequest, with
         * the additional "showException" flag.
         */
        $subRequest = $request->duplicate(null, null, [
            '_controller' => $this->controller,
            'exception' => new HttpException($code, 'This is a sample exception.'),
            'logger' => null,
            'showException' => false,
        ]);

        return $this->kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }
}

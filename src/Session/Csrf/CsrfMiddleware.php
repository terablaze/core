<?php

declare(strict_types=1);

namespace TeraBlaze\Session\Csrf;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TeraBlaze\Session\Flash\FlashMessageMiddleware;
use TeraBlaze\Session\Flash\FlashMessagesInterface;
use TeraBlaze\Session\SessionInterface;
use TeraBlaze\Session\SessionMiddleware;

/**
 * Injects a CSRF guard as a request attribute.
 *
 * Later middleware can then access the CsrfGuardInterface instance in order to
 * either generate or validate a token.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    public const GUARD_ATTRIBUTE = 'csrf';

    private string $guard = '';

    public function __construct($guard)
    {
        $this->guard = $guard;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        switch ($this->guard) {
            case "flash":
                $guard = container()->make(FlashCsrfGuard::class, [
                    'class' => FlashCsrfGuard::class,
                    'alias' => CsrfGuardInterface::class,
                    'arguments' => [
                        $request->getAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE) ??
                        $request->getAttribute(FlashMessagesInterface::class),
                    ]
                ]);
                break;
            case "session":
                $guard = container()->make(SessionCsrfGuard::class, [
                    'class' => SessionCsrfGuard::class,
                    'alias' => CsrfGuardInterface::class,
                    'arguments' => [
                        $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE) ??
                        $request->getAttribute(SessionInterface::class),
                    ]
                ]);
                break;
        }

        return $handler->handle($request
            ->withAttribute(self::GUARD_ATTRIBUTE, $guard)
            ->withAttribute(CsrfGuardInterface::class, $guard));
    }
}

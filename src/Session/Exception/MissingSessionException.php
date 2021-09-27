<?php

declare(strict_types=1);

namespace TeraBlaze\Session\Exception;

use Psr\Http\Server\MiddlewareInterface;
use TeraBlaze\Session\Csrf\SessionCsrfGuard;
use TeraBlaze\Session\SessionMiddleware;
use RuntimeException;

use function sprintf;

class MissingSessionException extends RuntimeException implements ExceptionInterface
{
    public static function create(): self
    {
        return new self(sprintf(
            'Cannot create %s; could not locate session in request. '
            . 'Make sure the %s is piped to your application.',
            SessionCsrfGuard::class,
            SessionMiddleware::class
        ));
    }

    public static function forFlashMiddleware(MiddlewareInterface $middleware): MissingSessionException
    {
        return new self(sprintf(
            'Unable to create flash messages in %s; missing session attribute',
            get_class($middleware)
        ));
    }
}

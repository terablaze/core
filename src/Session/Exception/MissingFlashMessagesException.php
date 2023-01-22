<?php

declare(strict_types=1);

namespace Terablaze\Session\Exception;

use Terablaze\Session\Csrf\FlashCsrfGuard;
use Terablaze\Session\Flash\FlashMessageMiddleware;
use RuntimeException;

use function sprintf;

class MissingFlashMessagesException extends RuntimeException implements ExceptionInterface
{
    public static function create(): self
    {
        return new self(sprintf(
            'Cannot create %s; could not locate session in request. '
            . 'Make sure the %s is piped to your application.',
            FlashCsrfGuard::class,
            FlashMessageMiddleware::class
        ));
    }
}

<?php

declare(strict_types=1);

namespace TeraBlaze\Session\Exception;

use InvalidArgumentException;
use TeraBlaze\Session\Flash\FlashMessageMiddleware;
use TeraBlaze\Session\Flash\FlashMessagesInterface;

use function sprintf;

class InvalidFlashMessagesImplementationException extends InvalidArgumentException implements ExceptionInterface
{
    public static function forClass(string $class): self
    {
        return new self(sprintf(
            'Cannot use "%s" within %s; does not implement %s',
            $class,
            FlashMessageMiddleware::class,
            FlashMessagesInterface::class
        ));
    }
}

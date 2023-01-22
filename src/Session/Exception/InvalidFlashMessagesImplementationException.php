<?php

declare(strict_types=1);

namespace Terablaze\Session\Exception;

use InvalidArgumentException;
use Terablaze\Session\Flash\FlashMessageMiddleware;
use Terablaze\Session\Flash\FlashMessagesInterface;

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

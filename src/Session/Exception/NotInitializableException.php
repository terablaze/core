<?php

namespace TeraBlaze\Session\Exception;

use TeraBlaze\Session\InitializePersistenceIdInterface;
use RuntimeException;
use TeraBlaze\Session\Persistence\SessionPersistenceInterface;

use function get_class;
use function sprintf;

final class NotInitializableException extends RuntimeException implements ExceptionInterface
{
    public static function invalidPersistence(SessionPersistenceInterface $persistence): self
    {
        return new self(sprintf(
            "Persistence '%s' does not implement '%s'",
            get_class($persistence),
            InitializePersistenceIdInterface::class
        ));
    }
}

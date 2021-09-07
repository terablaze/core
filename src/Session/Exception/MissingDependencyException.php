<?php

/**
 * @see       https://github.com/mezzio/mezzio-session-cache for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session-cache/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session-cache/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace TeraBlaze\Session\Exception;

use RuntimeException;
use TeraBlaze\Session\Persistence\CacheSessionPersistence;

use function sprintf;

class MissingDependencyException extends RuntimeException implements ExceptionInterface
{
    public static function forService(string $serviceName): self
    {
        return new self(sprintf(
            '%s requires the service "%s" in order to build a %s instance; none found',
            CacheSessionPersistenceFactory::class,
            $serviceName,
            CacheSessionPersistence::class
        ));
    }
}

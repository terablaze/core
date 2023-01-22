<?php

namespace Terablaze\Cache\Exception;

use Psr\Cache\CacheException as Psr6Exception;
use Psr\SimpleCache\CacheException as Psr16Exception;
use RuntimeException;

class ServiceException extends RuntimeException implements Psr16Exception, Psr6Exception
{
}

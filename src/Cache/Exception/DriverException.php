<?php

namespace Terablaze\Cache\Exception;

use Psr\SimpleCache\CacheException as Psr16Exception;
use Psr\Cache\CacheException as Psr6Exception;
use RuntimeException;

class DriverException extends RuntimeException implements Psr16Exception, Psr6Exception
{
}

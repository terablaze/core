<?php

namespace TeraBlaze\Cache\Exception;

use Psr\SimpleCache\CacheException;
use RuntimeException;

class ServiceException extends RuntimeException implements CacheException
{
}

<?php

namespace TeraBlaze\Cache\Exception;

use Psr\SimpleCache\CacheException;
use RuntimeException;

class DriverException extends RuntimeException implements CacheException
{
}
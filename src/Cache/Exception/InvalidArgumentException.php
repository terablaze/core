<?php

namespace Terablaze\Cache\Exception;

use Psr\SimpleCache\InvalidArgumentException as Psr16Exception;
use Psr\Cache\InvalidArgumentException as Psr6Exception;

class InvalidArgumentException extends \InvalidArgumentException implements Psr16Exception, Psr6Exception
{
}

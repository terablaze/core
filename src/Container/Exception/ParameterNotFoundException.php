<?php

namespace Terablaze\Container\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class ParameterNotFoundException extends Exception implements NotFoundExceptionInterface
{
}

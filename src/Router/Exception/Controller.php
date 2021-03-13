<?php

namespace TeraBlaze\Router\Exception;

use TeraBlaze\ErrorHandler\Exception\Http\NotFoundHttpException;
use TeraBlaze\Router\Exception\Exception;

class Controller extends NotFoundHttpException
{
}

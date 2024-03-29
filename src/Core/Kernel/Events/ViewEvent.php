<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Terablaze\Core\Kernel\Events;

use Terablaze\Core\Kernel\HttpKernelInterface;
use Terablaze\HttpBase\Request;

/**
 * Allows to create a response for the return value of a controller.
 *
 * Call setResponse() to set the response that will be returned for the
 * current request. The propagation of this event is stopped as soon as a
 * response is set.
 */
final class ViewEvent extends RequestEvent
{
    /**
     * The return value of the controller.
     *
     * @var mixed
     */
    private $controllerResult;

    public function __construct(HttpKernelInterface $kernel, Request $request, $controllerResult)
    {
        parent::__construct($kernel, $request);

        $this->controllerResult = $controllerResult;
    }

    /**
     * Returns the return value of the controller.
     *
     * @return mixed The controller return value
     */
    public function getControllerResult()
    {
        return $this->controllerResult;
    }

    /**
     * Assigns the return value of the controller.
     *
     * @param mixed $controllerResult The controller return value
     */
    public function setControllerResult($controllerResult): void
    {
        $this->controllerResult = $controllerResult;
    }
}

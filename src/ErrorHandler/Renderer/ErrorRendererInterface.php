<?php

namespace Terablaze\ErrorHandler\Renderer;

use Terablaze\ErrorHandler\FlattenException;

/**
 * Formats an exception to be used as response content.
 */
interface ErrorRendererInterface
{
    /**
     * Renders a Throwable as a FlattenException.
     */
    public function render(\Throwable $exception): FlattenException;
}

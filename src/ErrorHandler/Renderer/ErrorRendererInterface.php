<?php

namespace TeraBlaze\ErrorHandler\Renderer;

use TeraBlaze\ErrorHandler\FlattenException;

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

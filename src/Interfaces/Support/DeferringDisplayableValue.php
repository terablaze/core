<?php

namespace TeraBlaze\Interfaces\Support;

interface DeferringDisplayableValue
{
    /**
     * Resolve the displayable value that the class is deferring.
     *
     * @return \TeraBlaze\Interfaces\Support\Htmlable|string
     */
    public function resolveDisplayableValue();
}

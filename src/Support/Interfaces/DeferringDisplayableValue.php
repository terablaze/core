<?php

namespace Terablaze\Support\Interfaces;

interface DeferringDisplayableValue
{
    /**
     * Resolve the displayable value that the class is deferring.
     *
     * @return \Terablaze\Support\Interfaces\Htmlable|string
     */
    public function resolveDisplayableValue();
}

<?php

namespace Terablaze\Console\View\Components\Mutators;

class EnsureRelativePaths
{
    /**
     * Ensures the given string only contains relative paths.
     *
     * @param  string  $string
     * @return string
     */
    public function __invoke($string)
    {
        return str_replace(baseDir().'/', '', $string);
    }
}

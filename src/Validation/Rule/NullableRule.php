<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;
use TeraBlaze\Support\StringMethods;

class NullableRule extends Rule implements RuleInterface
{
    use SizeAwareTrait;

    public function validate(): bool
    {
        return true;
    }
}

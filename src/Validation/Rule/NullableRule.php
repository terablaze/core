<?php

namespace TeraBlaze\Validation\Rule;

use TeraBlaze\Validation\Rule\Traits\SizeAwareTrait;

class NullableRule extends Rule implements RuleInterface
{
    use SizeAwareTrait;

    public function validate(): bool
    {
        return true;
    }
}

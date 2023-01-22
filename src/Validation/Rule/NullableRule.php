<?php

namespace Terablaze\Validation\Rule;

use Terablaze\Validation\Rule\Traits\SizeAwareTrait;

class NullableRule extends Rule implements RuleInterface
{
    use SizeAwareTrait;

    public function validate(): bool
    {
        return true;
    }
}

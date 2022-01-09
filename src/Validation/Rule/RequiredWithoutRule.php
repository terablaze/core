<?php

namespace TeraBlaze\Validation\Rule;

use TeraBlaze\Validation\Rule\Traits\RequiredTestTrait;

class RequiredWithoutRule extends Rule implements RuleInterface
{
    use RequiredTestTrait;

    public function validate(): bool
    {
        if ($this->anyParamsEmpty()) {
            return !empty($this->value);
        }
        return true;
    }

    public function getMessage(): string
    {
        $dependantFmt = implode(" or ", $this->params);
        return $this->message ?? ":Field is required if any of $dependantFmt is empty or not present";
    }
}

<?php

namespace TeraBlaze\Validation\Rule;

use TeraBlaze\Validation\Rule\Traits\RequiredTestTrait;

class RequiredWithoutAllRule extends Rule implements RuleInterface
{
    use RequiredTestTrait;

    public function validate(): bool
    {
        if ($this->allParamsEmpty()) {
            return !empty($this->value);
        }
        return true;
    }

    public function getMessage(): string
    {
        $dependantFmt = implode(" and ", $this->params);
        return $this->message ?? ":Field is required if all of $dependantFmt are empty or not present";
    }
}

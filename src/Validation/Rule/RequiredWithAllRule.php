<?php

namespace TeraBlaze\Validation\Rule;

use TeraBlaze\Validation\Rule\Traits\RequiredTestTrait;

class RequiredWithAllRule extends Rule implements RuleInterface
{
    use RequiredTestTrait;

    public function validate(): bool
    {
        if ($this->anyParamsEmpty()) {
            return true;
        }
        return !empty($this->value);
    }

    public function getMessage(): string
    {
        $dependantFmt = implode(" and ", $this->params);
        return $this->message ?? ":Field is required if all of $dependantFmt are present and not empty";
    }
}

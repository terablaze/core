<?php

namespace Terablaze\Validation\Rule;

use Terablaze\Validation\Rule\Traits\RequiredTestTrait;

class RequiredWithRule extends Rule implements RuleInterface
{
    use RequiredTestTrait;

    public function validate(): bool
    {
        if ($this->allParamsEmpty()) {
            return true;
        }
        return !empty($this->value);
    }

    public function getMessage(): string
    {
        return $this->message ?? "The :field field is required when :values is present.";
    }
}

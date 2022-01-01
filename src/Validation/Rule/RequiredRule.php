<?php

namespace TeraBlaze\Validation\Rule;

class RequiredRule extends Rule implements RuleInterface
{
    protected string $message = ":field is required";

    public function validate(): bool
    {
        return !empty($this->value);
    }
}

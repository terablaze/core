<?php

namespace Terablaze\Validation\Rule;

class RequiredRule extends Rule implements RuleInterface
{
    protected ?string $message = ":Field is required";

    public function validate(): bool
    {
        return !empty($this->value);
    }
}

<?php

namespace TeraBlaze\Validation\Rule;

class IntRule extends Rule implements RuleInterface
{
    protected ?string $message = ":field must be an integer";

    public function validate(): bool
    {
        return is_int($this->value);
    }
}

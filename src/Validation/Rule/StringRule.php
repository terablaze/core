<?php

namespace TeraBlaze\Validation\Rule;

class StringRule extends Rule implements RuleInterface
{
    protected ?string $message = ":field must be a string";

    public function validate(): bool
    {
        return is_string($this->value);
    }
}

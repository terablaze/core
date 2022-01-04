<?php

namespace TeraBlaze\Validation\Rule;

class FloatRule extends Rule implements RuleInterface
{
    protected ?string $message = ":Field must be a float";

    public function validate(): bool
    {
        return is_float($this->value);
    }
}

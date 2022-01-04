<?php

namespace TeraBlaze\Validation\Rule;

class BoolableRule extends Rule implements RuleInterface
{
    protected ?string $message = ":Field must be resolvable to a boolean";

    public function validate(): bool
    {
        return filter_var($this->value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}

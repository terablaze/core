<?php

namespace TeraBlaze\Validation\Rule;

class BoolRule extends Rule implements RuleInterface
{
    protected ?string $message = ":Field must be a boolean";

    public function validate(): bool
    {
        return is_bool($this->value);
    }
}

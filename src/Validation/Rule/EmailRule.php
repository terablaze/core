<?php

namespace TeraBlaze\Validation\Rule;

class EmailRule extends Rule implements RuleInterface
{
    protected ?string $message = ":Field should be an email";

    public function validate(): bool
    {
        return filter_var($this->value, FILTER_VALIDATE_EMAIL);
    }
}

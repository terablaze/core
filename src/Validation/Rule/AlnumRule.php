<?php

namespace TeraBlaze\Validation\Rule;

class AlnumRule extends Rule implements RuleInterface
{
    protected ?string $message = ":field must contain only alphanumeric characters";

    public function validate(): bool
    {
        return ctype_alnum($this->value);
    }
}

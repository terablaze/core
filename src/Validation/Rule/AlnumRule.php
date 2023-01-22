<?php

namespace Terablaze\Validation\Rule;

class AlnumRule extends Rule implements RuleInterface
{
    protected ?string $message = ":Field must contain only alphanumeric characters";

    public function validate(): bool
    {
        return ctype_alnum($this->value);
    }
}

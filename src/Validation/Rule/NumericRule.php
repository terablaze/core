<?php

namespace Terablaze\Validation\Rule;

class NumericRule extends Rule implements RuleInterface
{
    protected ?string $message = ":Field must be a numeric data";

    public function validate(): bool
    {
        return is_numeric($this->value);
    }
}

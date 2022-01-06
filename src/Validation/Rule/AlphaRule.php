<?php

namespace TeraBlaze\Validation\Rule;

class AlphaRule extends Rule implements RuleInterface
{
    protected ?string $message = ":Field must contain only alphabets";

    public function validate(): bool
    {
        return ctype_alpha($this->value);
    }
}

<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;
use TeraBlaze\Support\StringMethods;

class MinRule extends Rule implements RuleInterface
{
    public function validate(): bool
    {
        if (empty($this->params[0])) {
            throw new InvalidArgumentException('specify a min length');
        }

        $length = (int) $this->params[0];

        return StringMethods::length($this->value) >= $length;
    }

    public function getMessage(): string
    {
        $length = (int) $this->params[0];

        return ":field should be at least {$length} characters";
    }
}

<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;
use TeraBlaze\Support\StringMethods;

class EqualsRule extends Rule implements RuleInterface
{
    public function validate(): bool
    {
        if (empty($this->params[0])) {
            throw new InvalidArgumentException('Specify a size');
        }

        $size = (int) $this->params[0];

        return $this->value == $size;
    }

    public function getMessage(): string
    {
        $size = (int) $this->params[0];

        return $this->message ?? ":field should be equal to $size";
    }
}

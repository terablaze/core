<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;
use TeraBlaze\Support\StringMethods;

class LteRule extends Rule implements RuleInterface
{
    public function validate(): bool
    {
        if (empty($this->params[0])) {
            throw new InvalidArgumentException('Specify a max size');
        }

        $size = (int) $this->params[0];

        return $this->value <= $size;
    }

    public function getMessage(): string
    {
        $size = (int) $this->params[0];

        return $this->message ?? ":Field should be less than or equal to $size";
    }
}

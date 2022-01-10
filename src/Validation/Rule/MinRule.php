<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;
use TeraBlaze\Validation\Rule\Traits\SizeAwareTrait;

class MinRule extends Rule implements RuleInterface
{
    use SizeAwareTrait;

    public function validate(): bool
    {
        if (empty($this->params[0])) {
            throw new InvalidArgumentException('specify a min size/length');
        }

        $min = (int) $this->params[0];

        return $this->getSize($this->value) >= $min;
    }

    public function getMessage(): string
    {
        $length = (int) $this->params[0];

        return $this->message ??
            trim(":Field should {$this->messageModifier['presence']} at least $length {$this->messageModifier['unit']}");
    }
}

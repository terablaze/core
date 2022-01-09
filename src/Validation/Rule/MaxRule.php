<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;
use TeraBlaze\Validation\Rule\Traits\SizeAwareTrait;

class MaxRule extends Rule implements RuleInterface
{
    use SizeAwareTrait;

    public function validate(): bool
    {
        if (empty($this->params[0])) {
            throw new InvalidArgumentException('specify a max size/length');
        }

        $max = (int) $this->params[0];
        return $this->getSize() <= $max;
    }

    public function getMessage(): string
    {
        $length = (int) $this->params[0];

        return $this->message ??
            trim(":Field should {$this->messageModifier['presence']} at most $length {$this->messageModifier['unit']}");
    }
}

<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;
use TeraBlaze\Support\StringMethods;

class ExactRule extends Rule implements RuleInterface
{
    use SizeAwareTrait;

    public function validate(): bool
    {
        if (empty($this->params[0])) {
            throw new InvalidArgumentException('specify a size/length');
        }

        $size = (int) $this->params[0];

        return $this->getSize() >= $size;
    }

    public function getMessage(): string
    {
        $length = (int) $this->params[0];

        return $this->message ??
            trim(":Field should {$this->messageModifier['presence']} exactly $length {$this->messageModifier['unit']}");
    }
}

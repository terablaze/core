<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;
use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Validation\Rule\Traits\SizeAwareTrait;

class ERule extends Rule implements RuleInterface
{
    use SizeAwareTrait;

    public function validate(): bool
    {
        if (empty($this->params[0])) {
            throw new InvalidArgumentException('specify a size/length');
        }

        $comparedToValue = ArrayMethods::get($this->data, $this->params[0]);

        if (is_null($comparedToValue) && (is_numeric($this->value) && is_numeric($this->params[0]))) {
            return $this->getSize($this->value) == $this->params[0];
        }

        if (is_numeric($this->params[0])) {
            return false;
        }

        if (is_numeric($this->value) && is_numeric($comparedToValue)) {
            return $this->value == $comparedToValue;
        }

        if (! $this->isSameType($this->value, $comparedToValue)) {
            return false;
        }

        return $this->getSize($this->value) == $this->getSize($comparedToValue);
    }

    public function getMessage(): string
    {
        $length = (int) $this->params[0];

        return $this->message ??
            trim(":Field should {$this->messageModifier['presence']} exactly $length {$this->messageModifier['unit']}");
    }
}

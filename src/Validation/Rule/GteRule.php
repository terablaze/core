<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;
use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Support\StringMethods;
use TeraBlaze\Validation\Rule\Traits\SizeAwareTrait;

class GteRule extends Rule implements RuleInterface
{
    use SizeAwareTrait;

    public function validate(): bool
    {
        if (empty($this->params[0])) {
            throw new InvalidArgumentException('Specify a min size');
        }

        $comparedToValue = ArrayMethods::get($this->data, $this->params[0]);

        if (is_null($comparedToValue) && (is_numeric($this->value) && is_numeric($this->params[0]))) {
            return $this->getSize($this->field, $this->value) >= $this->params[0];
        }

        if (is_numeric($this->params[0])) {
            return false;
        }

        if (is_numeric($this->value) && is_numeric($comparedToValue)) {
            return $this->value >= $comparedToValue;
        }

        if (! $this->isSameType($this->value, $comparedToValue)) {
            return false;
        }

        return $this->getSize($this->field, $this->value) >= $this->getSize($this->params[0], $comparedToValue);
    }

    public function getMessage(): string
    {
        return $this->message ?? ":Field should be greater than or equal to :value";
    }
}

<?php

namespace Terablaze\Validation\Rule;

use Terablaze\Support\ArrayMethods;

class ConfirmedRule extends Rule implements RuleInterface
{
    public function validate(): bool
    {
        $confirmation = ArrayMethods::get(
            $this->data,
            $this->params[0] . '-confirmation',
            ArrayMethods::get($this->data, $this->params[0] . '_confirmation')
        );
        return $this->value == $confirmation;
    }

    public function getMessage(): string
    {
        return $this->message ?? "The :other field must be present and must be equal to :field";
    }
}

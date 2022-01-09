<?php

namespace TeraBlaze\Validation\Rule;

use TeraBlaze\Support\ArrayMethods;
use TeraBlaze\Validation\Rule\Traits\RequiredTestTrait;

class SameRule extends Rule implements RuleInterface
{
    public function validate(): bool
    {
        return $this->value == ArrayMethods::get($this->data, $this->params[0]);
    }

    public function getMessage(): string
    {
        return $this->message ?? "The :field and :other must match";
    }
}

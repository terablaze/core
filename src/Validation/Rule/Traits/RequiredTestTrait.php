<?php

namespace Terablaze\Validation\Rule\Traits;

use Terablaze\Support\ArrayMethods;

trait RequiredTestTrait
{
    private function anyParamsEmpty(): bool
    {
        foreach ($this->params as $field) {
            if (empty(ArrayMethods::get($this->data, $field))) {
                return true;
            }
        }
        return false;
    }

    private function allParamsEmpty(): bool
    {
        foreach ($this->params as $field) {
            if (!empty(ArrayMethods::get($this->data, $field))) {
                return false;
            }
        }
        return true;
    }
}

<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;
use TeraBlaze\Support\StringMethods;

class MinRule extends Rule implements RuleInterface
{
    public function validate($data, string $field, array $params)
    {
        if (empty($params[0])) {
            throw new InvalidArgumentException('specify a min length');
        }

        $length = (int) $params[0];

        return StringMethods::length($data) >= $length;
    }

    public function getMessage($data, string $field, array $params)
    {
        $length = (int) $params[0];

        return $this->message ?? "{$field} should be at least {$length} characters";
    }
}

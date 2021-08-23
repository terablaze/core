<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;

class MinRule extends Rule implements RuleInterface
{
    public function validate(array $data, string $field, array $params)
    {
        if (empty($data[$field])) {
            return true;
        }

        if (empty($params[0])) {
            throw new InvalidArgumentException('specify a min length');
        }

        $length = (int) $params[0];

        return strlen($data[$field]) >= $length;
    }

    public function getMessage(array $data, string $field, array $params)
    {
        $length = (int) $params[0];

        return $this->message ?? "{$field} should be at least {$length} characters";
    }
}

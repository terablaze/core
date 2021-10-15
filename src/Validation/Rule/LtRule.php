<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;
use TeraBlaze\Support\StringMethods;

class LtRule extends Rule implements RuleInterface
{
    public function validate($data, string $field, array $params)
    {
        if (empty($params[0])) {
            throw new InvalidArgumentException('Specify a max size + 1');
        }

        $size = (int) $params[0];

        return $data < $size;
    }

    public function getMessage($data, string $field, array $params)
    {
        $size = (int) $params[0];

        return $this->message ?? "$field should be less than $size";
    }
}

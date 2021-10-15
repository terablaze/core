<?php

namespace TeraBlaze\Validation\Rule;

use InvalidArgumentException;
use TeraBlaze\Support\StringMethods;

class LteRule extends Rule implements RuleInterface
{
    public function validate($data, string $field, array $params)
    {
        if (empty($params[0])) {
            throw new InvalidArgumentException('Specify a max size');
        }

        $size = (int) $params[0];

        return $data <= $size;
    }

    public function getMessage($data, string $field, array $params)
    {
        $size = (int) $params[0];

        return $this->message ?? "$field should be less than or equal to $size";
    }
}

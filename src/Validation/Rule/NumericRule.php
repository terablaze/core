<?php

namespace TeraBlaze\Validation\Rule;

class NumericRule extends Rule implements RuleInterface
{
    public function validate($data, string $field, array $params)
    {
        return is_int($data);
    }

    public function getMessage($data, string $field, array $params)
    {
        return $this->message ?? "{$field} must be a numeric data";
    }
}

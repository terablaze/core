<?php

namespace TeraBlaze\Validation\Rule;

class FloatRule extends Rule implements RuleInterface
{
    public function validate($data, string $field, array $params)
    {
        return is_float($data);
    }

    public function getMessage($data, string $field, array $params)
    {
        return $this->message ?? "{$field} must be a float";
    }
}

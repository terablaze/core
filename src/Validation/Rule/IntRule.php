<?php

namespace TeraBlaze\Validation\Rule;

class IntRule extends Rule implements RuleInterface
{
    public function validate($data, string $field, array $params)
    {
        return is_int($data);
    }

    public function getMessage($data, string $field, array $params)
    {
        return $this->message ?? "{$field} must be an integer";
    }
}

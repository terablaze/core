<?php

namespace TeraBlaze\Validation\Rule;

class BoolableRule extends Rule implements RuleInterface
{
    public function validate($data, string $field, array $params)
    {
        return filter_var($data, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public function getMessage($data, string $field, array $params)
    {
        return $this->message ?? "{$field} must be a resolvable to a boolean";
    }
}

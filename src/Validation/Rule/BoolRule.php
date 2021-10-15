<?php

namespace TeraBlaze\Validation\Rule;

class BoolRule extends Rule implements RuleInterface
{
    public function validate($data, string $field, array $params)
    {
        return is_bool($data);
    }

    public function getMessage($data, string $field, array $params)
    {
        return $this->message ?? "{$field} must be a boolean";
    }
}

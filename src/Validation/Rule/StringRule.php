<?php

namespace TeraBlaze\Validation\Rule;

class StringRule extends Rule implements RuleInterface
{
    public function validate($data, string $field, array $params)
    {
        return is_string($data);
    }

    public function getMessage($data, string $field, array $params)
    {
        return $this->message ?? "{$field} must be a string";
    }
}

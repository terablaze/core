<?php

namespace TeraBlaze\Validation\Rule;

class RequiredRule extends Rule implements RuleInterface
{
    public function validate(array $data, string $field, array $params)
    {
        return !empty($data[$field]);
    }

    public function getMessage(array $data, string $field, array $params)
    {
        return $this->message ?? "{$field} is required";
    }
}

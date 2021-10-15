<?php

namespace TeraBlaze\Validation\Rule;

class RequiredRule extends Rule implements RuleInterface
{
    public function validate($data, string $field, array $params)
    {
        return !empty($data);
    }

    public function getMessage($data, string $field, array $params)
    {
        return $this->message ?? "{$field} is required";
    }
}

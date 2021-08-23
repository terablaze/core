<?php

namespace TeraBlaze\Validation\Rule;

class EmailRule extends Rule implements RuleInterface
{
    public function validate(array $data, string $field, array $params)
    {
        if (empty($data[$field])) {
            return true;
        }

        return filter_var($data[$field], FILTER_VALIDATE_EMAIL);
    }

    public function getMessage(array $data, string $field, array $params)
    {
        return $this->message ?? "{$field} should be an email";
    }
}

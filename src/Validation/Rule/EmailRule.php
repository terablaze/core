<?php

namespace TeraBlaze\Validation\Rule;

class EmailRule extends Rule implements RuleInterface
{
    public function validate($data, string $field, array $params)
    {
        if (empty($data)) {
            return true;
        }

        return filter_var($data, FILTER_VALIDATE_EMAIL);
    }

    public function getMessage($data, string $field, array $params)
    {
        return $this->message ?? "{$field} should be an email";
    }
}

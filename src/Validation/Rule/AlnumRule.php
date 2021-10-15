<?php

namespace TeraBlaze\Validation\Rule;

class AlnumRule extends Rule implements RuleInterface
{
    public function validate($data, string $field, array $params)
    {
        return ctype_alnum($data);
    }

    public function getMessage($data, string $field, array $params)
    {
        return $this->message ?? "{$field} must contain only alphanumeric characters";
    }
}

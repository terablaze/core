<?php

namespace TeraBlaze\Validation\Rule;

class AlphaRule extends Rule implements RuleInterface
{
    public function validate($data, string $field, array $params)
    {
        return ctype_alpha($data);
    }

    public function getMessage($data, string $field, array $params)
    {
        return $this->message ?? "{$field} must contain only alphabets";
    }
}

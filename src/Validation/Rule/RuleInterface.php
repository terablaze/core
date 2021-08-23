<?php

namespace TeraBlaze\Validation\Rule;

interface RuleInterface
{
    public function validate(array $data, string $field, array $params);

    public function getMessage(array $data, string $field, array $params);
}

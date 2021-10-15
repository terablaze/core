<?php

namespace TeraBlaze\Validation\Rule;

interface RuleInterface
{
    public function validate($data, string $field, array $params);

    public function getMessage($data, string $field, array $params);
}

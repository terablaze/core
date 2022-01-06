<?php

namespace TeraBlaze\Validation;

use TeraBlaze\Validation\Rule\RuleInterface;

interface ValidatorInterface
{
    public function addRule(string $alias, RuleInterface $rule): \TeraBlaze\Validation\Validator;

    public function validate(array $data, array $rules, array $messages = [], array $customAttributes = []): array;
}
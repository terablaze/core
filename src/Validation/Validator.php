<?php

namespace TeraBlaze\Validation;

use TeraBlaze\Validation\Exception\ValidationException;
use TeraBlaze\Validation\Rule\RuleInterface;

class Validator
{
    /** @var RuleInterface[] */
    protected array $rules = [];

    public function addRule(string $alias, RuleInterface $rule): self
    {
        $this->rules[$alias] = $rule;
        return $this;
    }

    public function validate(array $data, array $rules, string $sessionName = 'errors'): array
    {
        $errors = [];

        foreach ($rules as $field => $rulesForField) {
            foreach ($rulesForField as $rule) {
                $name = $rule;
                $params = [];

                if (str_contains($rule, ':')) {
                    [$name, $params] = explode(':', $rule);
                    $params = explode(',', $params);
                }

                $processor = $this->rules[$name];

                if (!$processor->validate($data, $field, $params)) {
                    if (!isset($errors[$field])) {
                        $errors[$field] = [];
                    }

                    array_push($errors[$field], $processor->getMessage($data, $field, $params));
                }
            }
        }

        if (count($errors)) {
            $exception = new ValidationException();
            $exception->setErrors($errors);
            $exception->setSessionName($sessionName);
            throw $exception;
        } else {
            if ($session = session()) {
                $session->forget($sessionName);
            }
        }

        return array_intersect_key($data, $rules);
    }
}

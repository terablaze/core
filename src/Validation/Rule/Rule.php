<?php

namespace TeraBlaze\Validation\Rule;

abstract class Rule implements RuleInterface
{
    protected string $message;

    abstract public function validate(array $data, string $field, array $params);

    abstract public function getMessage(array $data, string $field, array $params);

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }
}
